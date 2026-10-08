<?php

namespace App\Http\Controllers;

use App\Http\Requests\Checkout\PlaceOrderRequest;
use App\Mail\PaymentAmountMismatchMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\PayPalService;
use App\Support\Cart\CartService;
use App\Support\Orders\OrderManager;
use App\Support\Orders\OrderStatus;
use App\Support\Orders\PaymentMethod;
use App\Support\Orders\PaymentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Response as InertiaResponse;

class PayPalController extends Controller
{
    private const PENDING_MESSAGE = 'Ihre Zahlung wird von PayPal noch geprüft. Sie erhalten eine Bestätigung per E-Mail, sobald sie abgeschlossen ist. Bitte zahlen Sie nicht erneut.';

    public function __construct(
        private readonly PayPalService $payPalService,
        private readonly CartService $cartService,
        private readonly OrderManager $orderManager,
    ) {}

    /**
     * Step 1: Create a PayPal Order via REST API.
     *
     * Called from the frontend PayPal button. Creates the order in PayPal
     * and returns the Order ID so the JS SDK can launch the approval flow.
     */
    public function createOrder(PlaceOrderRequest $request): JsonResponse
    {
        $cart = $this->cartService->cart();

        if ($cart->isEmpty()) {
            return response()->json(['error' => 'Warenkorb ist leer.'], 400);
        }

        if (! $cart->shippingAddress->isComplete()) {
            return response()->json(['error' => 'Bitte vervollständigen Sie Ihre Lieferadresse.'], 422);
        }

        if ($cart->hasUnavailableItems()) {
            return response()->json(['error' => 'Ihr Warenkorb enthält nicht mehr verfügbare Produkte.'], 422);
        }

        // Each PayPal button click creates a fresh order; cancel earlier attempts
        // by this customer that were abandoned before approval, so a repeated
        // click (retry, closed popup, ...) doesn't pile up orphaned orders.
        $this->orderManager->cancelAbandonedPayPalOrders($request->user());

        $order = $this->orderManager->createFromCart($request->user(), $cart, PaymentMethod::Paypal);

        try {
            $response = $this->payPalService->createOrder(
                amount: $cart->totalCents() / 100,
                address: $cart->shippingAddress->toArray(),
            );

            if (! isset($response['id'])) {
                Log::error('PayPal createOrder failed', ['response' => $response]);

                // Wie im Exception-Pfad: sonst bliebe die Bestellung ohne
                // Zahlung als "offen" beim Kunden stehen.
                $this->orderManager->transitionTo($order, OrderStatus::Failed);

                return response()->json([
                    'error' => 'PayPal-Order konnte nicht erstellt werden.',
                ], 500);
            }

            // Save a payment record immediately with CREATED status. The cart is
            // intentionally NOT cleared here — only after a successful capture —
            // so an aborted PayPal approval leaves the cart intact.
            $this->payPalService->recordPayment($order, $response, PaymentStatus::Created);

            return response()->json([
                'id' => $response['id'],
                'status' => $response['status'],
            ]);
        } catch (\Throwable $e) {
            Log::error('PayPal createOrder exception', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
            ]);

            $this->orderManager->transitionTo($order, OrderStatus::Failed);

            return response()->json([
                'error' => 'PayPal-Zahlung konnte nicht initialisiert werden.',
            ], 500);
        }
    }

    /**
     * Step 2: Capture a PayPal Order after the buyer approved it.
     *
     * Called from the frontend after PayPal JS SDK approval.
     * On success: updates order to "paid" and payment to "COMPLETED".
     */
    public function captureOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'paypal_order_id' => 'required|string',
        ]);

        $paypalOrderId = $validated['paypal_order_id'];

        /** @var Payment|null $payment */
        $payment = Payment::query()
            ->where('paypal_order_id', $paypalOrderId)
            ->whereHas('order', fn ($q) => $q->where('customer_id', $request->user()?->id))
            ->with('order')
            ->first();

        if (! $payment) {
            return response()->json(['error' => 'Zahlung nicht gefunden.'], 404);
        }

        if ($payment->status === PaymentStatus::Completed) {
            return response()->json(['error' => 'Zahlung bereits abgeschlossen.'], 409);
        }

        try {
            $response = $this->payPalService->captureOrder($paypalOrderId);
            $result = $this->completeCapture($payment, $response);

            if ($result === 'completed') {
                return response()->json([
                    'status' => 'COMPLETED',
                    'paypal_order_id' => $paypalOrderId,
                ]);
            }

            if ($result === 'pending') {
                return response()->json([
                    'status' => 'PENDING',
                    'paypal_order_id' => $paypalOrderId,
                    'error' => self::PENDING_MESSAGE,
                ], 422);
            }

            if ($this->markCaptureFailed($payment, $response)) {
                return $this->alreadyCompletedResponse($paypalOrderId);
            }

            // Non-2xx, damit der PayPal-Button den Fehlerpfad nimmt statt auf
            // die Erfolgsseite weiterzuleiten (Kunde sähe sonst "Vielen Dank"
            // ohne bezahlte Order, ohne Mails).
            return response()->json([
                'status' => 'FAILED',
                'paypal_order_id' => $paypalOrderId,
                'error' => 'Die Zahlung wurde von PayPal nicht abgeschlossen. Bitte versuchen Sie es erneut oder wählen Sie eine andere Zahlungsart.',
            ], 422);
        } catch (\Throwable $e) {
            Log::error('PayPal captureOrder exception', [
                'paypal_order_id' => $paypalOrderId,
                'error' => $e->getMessage(),
            ]);

            if ($this->markCaptureFailed($payment, ['error' => $e->getMessage()])) {
                return $this->alreadyCompletedResponse($paypalOrderId);
            }

            return response()->json([
                'error' => 'Zahlung konnte nicht abgeschlossen werden.',
            ], 500);
        }
    }

    /**
     * Handle incoming PayPal webhook events.
     *
     * Verifies the webhook signature, then updates the payment and order status.
     */
    public function webhook(Request $request): Response
    {
        // Verify webhook authenticity
        if (! $this->payPalService->verifyWebhook($request)) {
            Log::warning('PayPal webhook verification failed', [
                'headers' => $request->headers->all(),
            ]);

            return response('Verification failed', 400);
        }

        $payload = $request->all();
        $eventType = $payload['event_type'] ?? '';
        $resource = $payload['resource'] ?? [];

        Log::info('PayPal webhook received', [
            'event_type' => $eventType,
            'resource_id' => $resource['id'] ?? null,
        ]);

        match ($eventType) {
            'CHECKOUT.ORDER.APPROVED' => $this->handleOrderApproved($resource),
            'PAYMENT.CAPTURE.COMPLETED' => $this->handleCaptureCompleted($resource),
            'PAYMENT.CAPTURE.REFUNDED' => $this->handleCaptureRefunded($resource),
            'PAYMENT.CAPTURE.DENIED' => $this->handleCaptureDenied($resource),
            'PAYMENT.CAPTURE.PENDING' => $this->handleCapturePending($resource),
            'PAYMENT.CAPTURE.REVERSED' => $this->handleCaptureReversed($resource),
            default => Log::info('PayPal webhook: unhandled event type', ['event_type' => $eventType]),
        };

        return response('', 200);
    }

    /**
     * After-payment return URL (when buyer is redirected back).
     * Looks up the most recent payment for the authenticated customer's order.
     */
    public function afterPayment(Request $request): RedirectResponse|InertiaResponse
    {
        $token = $request->query('token');

        if (! $token) {
            return to_route('checkout.index');
        }

        // Ownership-Scope wie beim JS-Capture (V4 S-2): sonst könnte ein
        // eingeloggter Dritter mit bekanntem Token fremde Orders capturen —
        // und completeCapture() würde dabei den Warenkorb der falschen
        // Session leeren.
        /** @var Payment|null $payment */
        $payment = Payment::query()
            ->where('paypal_order_id', $token)
            ->whereHas('order', fn ($q) => $q->where('customer_id', $request->user()?->id))
            ->with('order')
            ->first();

        if (! $payment) {
            return to_route('checkout.index');
        }

        if ($payment->status === PaymentStatus::Completed) {
            return to_route('checkout.success', ['paypal_order_id' => $token]);
        }

        // If still pending/created, try to capture
        try {
            $response = $this->payPalService->captureOrder($token);
            $result = $this->completeCapture($payment, $response);

            if ($result === 'completed') {
                return to_route('checkout.success', ['paypal_order_id' => $token]);
            }

            if ($result === 'pending') {
                return to_route('checkout.index')->with('error', self::PENDING_MESSAGE);
            }

            return $this->hasCompleted($payment)
                ? to_route('checkout.success', ['paypal_order_id' => $token])
                : to_route('checkout.error');
        } catch (\Throwable $e) {
            Log::error('PayPal afterPayment capture failed', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);

            return $this->hasCompleted($payment)
                ? to_route('checkout.success', ['paypal_order_id' => $token])
                : to_route('checkout.error');
        }
    }

    /**
     * Shared capture completion for the JS-SDK capture endpoint and the
     * return-URL fallback: verifies the captured amount against the order,
     * persists the capture on the payment, marks the order paid (idempotent)
     * and clears the cart.
     *
     * - "completed": bezahlt.
     * - "pending": PayPal prüft die Zahlung noch; Payment PENDING, die
     *   Bestellung bleibt offen, der Webhook entscheidet später.
     * - "failed": nicht abgeschlossen oder Betrag/Währung weichen ab; die
     *   Aufrufer markieren den Fehlschlag.
     *
     * @param  array<string, mixed>  $response
     * @return 'completed'|'pending'|'failed'
     */
    private function completeCapture(Payment $payment, array $response): string
    {
        // Maßgeblich ist der Status der Capture, nicht der der Order: PayPal
        // setzt die Order nach dem Capture auf COMPLETED, auch wenn die
        // Capture selbst noch PENDING (Prüfung) oder DECLINED ist.
        $capture = $response['purchase_units'][0]['payments']['captures'][0] ?? [];
        $captureStatus = $capture['status'] ?? 'FAILED';
        $capturedAmount = $capture['amount']['value'] ?? null;
        $capturedCurrency = $capture['amount']['currency_code'] ?? null;

        if ($captureStatus === 'COMPLETED' && ! $this->captureMatchesOrder($payment, $capturedAmount, $capturedCurrency)) {
            Log::error('PayPal capture amount mismatch', [
                'paypal_order_id' => $payment->paypal_order_id,
                'captured' => $capturedAmount,
                'currency' => $capturedCurrency,
                'expected' => $payment->order->total_amount,
            ]);

            $this->recordMismatchedCapture($payment, (string) ($capture['id'] ?? ''), $capture);

            return 'failed';
        }

        if ($captureStatus === 'PENDING') {
            Payment::query()
                ->whereKey($payment->getKey())
                ->whereIn('status', [PaymentStatus::Created, PaymentStatus::Approved, PaymentStatus::Failed])
                ->update([
                    'status' => PaymentStatus::Pending,
                    'paypal_capture_id' => $capture['id'] ?? null,
                    'response_data' => $response,
                ]);

            return 'pending';
        }

        if ($captureStatus !== 'COMPLETED') {
            return 'failed';
        }

        $payerName = trim(($response['payer']['name']['given_name'] ?? '').' '.($response['payer']['name']['surname'] ?? ''));

        $payment->update([
            'status' => PaymentStatus::Completed,
            'paypal_capture_id' => $this->payPalService->getCaptureIdFromOrder($response),
            // Eingezogener Betrag, Währung und Gebühr stehen erst in der
            // Capture-Antwort; die Admin-Erstattung erstattet genau diesen Betrag.
            'amount' => $capture['amount']['value'] ?? $payment->amount,
            'currency' => $capture['amount']['currency_code'] ?? $payment->currency,
            'fee' => $capture['seller_receivable_breakdown']['paypal_fee']['value'] ?? $payment->fee,
            'paypal_payer_id' => $response['payer']['payer_id'] ?? $payment->paypal_payer_id,
            'payer_email' => $response['payer']['email_address'] ?? $payment->payer_email,
            'payer_name' => $payerName !== '' ? $payerName : $payment->payer_name,
            'response_data' => $response,
        ]);

        $this->orderManager->markPaid($payment->order);
        $this->cartService->clear();

        return 'completed';
    }

    /**
     * Betrag und Währung der Capture gegen die Bestellung. Fehlt die Währung
     * in der Antwort, gilt die der Zahlung.
     */
    private function captureMatchesOrder(Payment $payment, mixed $capturedAmount, mixed $capturedCurrency): bool
    {
        $amountMatches = $capturedAmount === null
            || number_format((float) $capturedAmount, 2, '.', '') === number_format((float) $payment->order->total_amount, 2, '.', '');
        $currencyMatches = $capturedCurrency === null
            || strtoupper((string) $capturedCurrency) === strtoupper($payment->currency ?: (string) config('paypal.currency', 'EUR'));

        return $amountMatches && $currencyMatches;
    }

    /**
     * Eingezogen, aber Betrag oder Währung passen nicht zur Bestellung: Das
     * Geld liegt bei PayPal, die Bestellung wird nicht erfüllt. Die Capture
     * wird mit Betrag gespeichert (Status MISMATCH), damit der Admin sie
     * erstatten kann, und der Betreiber bekommt eine Mail (V4 B-1).
     *
     * @param  array<string, mixed>  $capture
     */
    private function recordMismatchedCapture(Payment $payment, string $captureId, array $capture): void
    {
        DB::transaction(function () use ($payment, $captureId, $capture): void {
            Payment::query()
                ->whereKey($payment->getKey())
                ->whereNotIn('status', [PaymentStatus::Completed, PaymentStatus::Refunded])
                ->update([
                    'status' => PaymentStatus::Mismatch,
                    'paypal_capture_id' => $captureId !== '' ? $captureId : null,
                    'amount' => $capture['amount']['value'] ?? $payment->amount,
                    'currency' => $capture['amount']['currency_code'] ?? $payment->currency,
                    'fee' => $capture['seller_receivable_breakdown']['paypal_fee']['value'] ?? $payment->fee,
                ]);

            $this->orderManager->transitionTo($payment->order, OrderStatus::Failed);
        });

        $this->notifyAmountMismatch($payment, (string) ($capture['amount']['value'] ?? ''));
    }

    /**
     * Mark a failed capture on payment and order without overwriting a
     * payment that Return-URL, JS-Capture or webhook completed in the
     * meantime (the losing capture request then fails at PayPal as "already
     * captured"). Returns true if the payment is in fact COMPLETED.
     *
     * @param  array<string, mixed>  $responseData
     */
    private function markCaptureFailed(Payment $payment, array $responseData): bool
    {
        return DB::transaction(function () use ($payment, $responseData): bool {
            /** @var Payment $current */
            $current = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if ($current->status === PaymentStatus::Completed) {
                return true;
            }

            // Eingezogene Capture mit Abweichung bleibt erstattbar.
            if ($current->status !== PaymentStatus::Mismatch) {
                $current->update(['status' => PaymentStatus::Failed, 'response_data' => $responseData]);
            }

            $this->orderManager->transitionTo($payment->order, OrderStatus::Failed);

            return false;
        });
    }

    private function hasCompleted(Payment $payment): bool
    {
        return Payment::query()->whereKey($payment->getKey())->value('status') === PaymentStatus::Completed;
    }

    private function alreadyCompletedResponse(string $paypalOrderId): JsonResponse
    {
        return response()->json([
            'status' => 'COMPLETED',
            'paypal_order_id' => $paypalOrderId,
        ]);
    }

    /**
     * Alert the operator about a capture whose amount does not match the
     * order: the money has already been collected at PayPal, but the order
     * is marked failed - this needs a manual look (refund or correction).
     */
    private function notifyAmountMismatch(Payment $payment, string $capturedAmount): void
    {
        $recipient = Setting::get('shop.email');

        if (! $recipient) {
            return;
        }

        try {
            Mail::to($recipient)->send(new PaymentAmountMismatchMail($payment, $capturedAmount));
        } catch (\Throwable $e) {
            Log::channel('mail')->error('Amount mismatch alert mail failed', [
                'order_id' => $payment->order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle CHECKOUT.ORDER.APPROVED webhook.
     *
     * @param  array<string, mixed>  $resource
     */
    private function handleOrderApproved(array $resource): void
    {
        $orderId = $resource['id'] ?? null;

        if (! $orderId) {
            return;
        }

        Payment::query()
            ->where('paypal_order_id', $orderId)
            ->where('status', PaymentStatus::Created)
            ->update(['status' => PaymentStatus::Approved]);
    }

    /**
     * Handle PAYMENT.CAPTURE.COMPLETED webhook.
     *
     * @param  array<string, mixed>  $resource
     */
    private function handleCaptureCompleted(array $resource): void
    {
        $captureId = $resource['id'] ?? null;

        if (! $captureId) {
            return;
        }

        /** @var Payment|null $payment */
        $payment = Payment::query()
            ->where('paypal_capture_id', $captureId)
            ->orWhere('response_data->purchase_units[0]->payments->captures[0]->id', $captureId)
            ->with('order')
            ->first();

        if (! $payment) {
            // Try to find by related order in resource
            $supplementaryData = $resource['supplementary_data'] ?? [];
            $relatedIds = $supplementaryData['related_ids'] ?? [];
            $orderId = $relatedIds['order_id'] ?? null;

            if ($orderId) {
                $payment = Payment::query()
                    ->where('paypal_order_id', $orderId)
                    ->with('order')
                    ->first();
            }
        }

        if (! $payment) {
            return;
        }

        // Ein Refund ist final: ein verspätet/erneut zugestelltes
        // CAPTURE.COMPLETED (PayPal liefert at-least-once, unsortiert) darf
        // Payment und Order nicht zurück auf COMPLETED/paid drehen (V4 S-1).
        if ($payment->status === PaymentStatus::Refunded) {
            Log::info('PayPal webhook: ignoring late capture for refunded payment', [
                'capture_id' => $captureId,
                'payment_id' => $payment->id,
            ]);

            return;
        }

        // Betragsabgleich wie im Capture-/Return-Pfad (V4 S-3). Signatur ist
        // verifiziert, die Capture stammt aus der eigenen Order - trotzdem
        // gilt dieselbe Defense-in-Depth-Regel für alle drei Pfade.
        $capturedAmount = $resource['amount']['value'] ?? null;
        $capturedCurrency = $resource['amount']['currency_code'] ?? null;

        if (! $this->captureMatchesOrder($payment, $capturedAmount, $capturedCurrency)) {
            Log::error('PayPal webhook capture amount mismatch', [
                'capture_id' => $captureId,
                'captured' => $capturedAmount,
                'currency' => $capturedCurrency,
                'expected' => $payment->order->total_amount,
            ]);

            if ($payment->status !== PaymentStatus::Completed) {
                $this->recordMismatchedCapture($payment, (string) $captureId, $resource);
            } else {
                $this->notifyAmountMismatch($payment, (string) $capturedAmount);
            }

            return;
        }

        // Bereits abgeschlossen (Normalfall: JS-Capture war schneller als der
        // Webhook): response_data nicht mit der Capture-Resource überschreiben
        // (der volle Order-Response ist der bessere Audit-Trail), nur den
        // idempotenten markPaid-Pfad sicherstellen.
        if ($payment->status !== PaymentStatus::Completed) {
            $payment->update([
                'status' => PaymentStatus::Completed,
                'paypal_capture_id' => $captureId,
                'amount' => $resource['amount']['value'] ?? $payment->amount,
                'currency' => $resource['amount']['currency_code'] ?? $payment->currency,
                'fee' => $resource['seller_receivable_breakdown']['paypal_fee']['value'] ?? $payment->fee,
                'response_data' => $resource,
            ]);
        }

        $this->orderManager->markPaid($payment->order);
    }

    /**
     * Handle PAYMENT.CAPTURE.REFUNDED webhook (e.g. refund issued directly
     * in the PayPal dashboard).
     *
     * @param  array<string, mixed>  $resource
     */
    private function handleCaptureRefunded(array $resource): void
    {
        $captureId = $this->captureIdFromRefundResource($resource);

        if (! $captureId) {
            return;
        }

        // Teilerstattungen (z.B. direkt im PayPal-Dashboard) erledigen die
        // Zahlung nicht: Payment und Order bleiben bezahlt, der Betreiber
        // sieht den Vorgang im Log.
        $refundedAmount = $resource['amount']['value'] ?? null;
        $payment = Payment::query()->where('paypal_capture_id', $captureId)->with('order')->first();

        if ($payment && $refundedAmount !== null && number_format((float) $refundedAmount, 2, '.', '') !== number_format($payment->capturedAmount(), 2, '.', '')) {
            Log::warning('PayPal webhook: partial refund ignored', [
                'capture_id' => $captureId,
                'refunded' => $refundedAmount,
                'captured' => $payment->capturedAmount(),
            ]);

            return;
        }

        $this->setStatusByCaptureId($captureId, PaymentStatus::Refunded, OrderStatus::Refunded);
    }

    /**
     * Handle PAYMENT.CAPTURE.DENIED webhook.
     *
     * @param  array<string, mixed>  $resource
     */
    private function handleCaptureDenied(array $resource): void
    {
        $captureId = $resource['id'] ?? null;

        if (! $captureId) {
            return;
        }

        // Webhooks kommen unsortiert: ein spätes DENIED darf eine bereits
        // bezahlte oder erstattete Order nicht auf failed zurückdrehen.
        $order = Order::query()
            ->whereHas('payments', fn ($q) => $q->where('paypal_capture_id', $captureId))
            ->first();

        if ($order && $order->status->isSettled()) {
            Log::info('PayPal webhook: ignoring late denial for settled order', [
                'capture_id' => $captureId,
                'order_id' => $order->id,
            ]);

            return;
        }

        $this->setStatusByCaptureId($captureId, PaymentStatus::Failed, OrderStatus::Failed);
    }

    /**
     * Handle PAYMENT.CAPTURE.PENDING webhook: PayPal prüft die Zahlung noch.
     * Die Bestellung bleibt offen; COMPLETED oder DENIED folgen per Webhook.
     *
     * @param  array<string, mixed>  $resource
     */
    private function handleCapturePending(array $resource): void
    {
        $captureId = $resource['id'] ?? null;
        $orderId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;

        if (! $captureId || ! $orderId) {
            return;
        }

        Payment::query()
            ->where('paypal_order_id', $orderId)
            ->whereIn('status', [PaymentStatus::Created, PaymentStatus::Approved, PaymentStatus::Failed])
            ->update(['status' => PaymentStatus::Pending, 'paypal_capture_id' => $captureId]);
    }

    /**
     * Handle PAYMENT.CAPTURE.REVERSED webhook: PayPal hat das Geld an den
     * Käufer zurückgebucht (z. B. nach einem Konflikt). Wirkt für den Shop
     * wie eine volle Erstattung, kommt aber nicht vom Shop.
     *
     * @param  array<string, mixed>  $resource
     */
    private function handleCaptureReversed(array $resource): void
    {
        $captureId = $this->captureIdFromRefundResource($resource);

        if (! $captureId) {
            return;
        }

        Log::warning('PayPal webhook: capture reversed by PayPal', ['capture_id' => $captureId]);

        $this->setStatusByCaptureId($captureId, PaymentStatus::Reversed, OrderStatus::Refunded);
    }

    /**
     * Setzt den Status der Zahlung zur Capture und den ihrer Bestellung in
     * einem Schritt (Erstattung, Ablehnung, Rückbuchung per Webhook). Ob der
     * Bestell-Übergang erlaubt ist, entscheidet der OrderManager.
     */
    private function setStatusByCaptureId(string $captureId, PaymentStatus $paymentStatus, OrderStatus $orderStatus): void
    {
        /** @var Payment|null $payment */
        $payment = Payment::query()
            ->where('paypal_capture_id', $captureId)
            ->with('order')
            ->first();

        if (! $payment) {
            Log::info('PayPal webhook: no payment for capture', ['capture_id' => $captureId]);

            return;
        }

        DB::transaction(function () use ($payment, $paymentStatus, $orderStatus): void {
            $payment->update(['status' => $paymentStatus]);

            if ($payment->order) {
                $this->orderManager->transitionTo($payment->order, $orderStatus);
            }
        });
    }

    /**
     * PAYMENT.CAPTURE.REFUNDED delivers a *refund* resource: its "id" is the
     * refund id, the capture id only appears in the "up" link
     * (/v2/payments/captures/{id}).
     *
     * @param  array<string, mixed>  $resource
     */
    private function captureIdFromRefundResource(array $resource): ?string
    {
        foreach ($resource['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === 'up' && str_contains((string) ($link['href'] ?? ''), '/captures/')) {
                return basename((string) parse_url($link['href'], PHP_URL_PATH));
            }
        }

        // Defensive fallback in case PayPal ever sends the capture itself.
        return $resource['id'] ?? null;
    }
}
