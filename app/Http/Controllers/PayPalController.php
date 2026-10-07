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
        $this->cancelStalePendingPayPalOrders($request->user());

        $order = $this->orderManager->createFromCart($request->user(), $cart, 'paypal');

        try {
            $response = $this->payPalService->createOrder(
                amount: $cart->totalCents() / 100,
                address: $cart->shippingAddress->toArray(),
            );

            if (! isset($response['id'])) {
                Log::error('PayPal createOrder failed', ['response' => $response]);

                return response()->json([
                    'error' => 'PayPal-Order konnte nicht erstellt werden.',
                ], 500);
            }

            // Save a payment record immediately with CREATED status. The cart is
            // intentionally NOT cleared here — only after a successful capture —
            // so an aborted PayPal approval leaves the cart intact.
            $this->payPalService->recordPayment($order, $response, 'CREATED');

            return response()->json([
                'id' => $response['id'],
                'status' => $response['status'],
            ]);
        } catch (\Throwable $e) {
            Log::error('PayPal createOrder exception', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
            ]);

            $order->update(['status' => 'failed']);

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

        if ($payment->status === 'COMPLETED') {
            return response()->json(['error' => 'Zahlung bereits abgeschlossen.'], 409);
        }

        try {
            $response = $this->payPalService->captureOrder($paypalOrderId);

            if ($this->completeCapture($payment, $response)) {
                return response()->json([
                    'status' => 'COMPLETED',
                    'paypal_order_id' => $paypalOrderId,
                ]);
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

        if ($payment->status === 'COMPLETED') {
            return to_route('checkout.success', ['paypal_order_id' => $token]);
        }

        // If still pending/created, try to capture
        try {
            $response = $this->payPalService->captureOrder($token);

            if ($this->completeCapture($payment, $response)) {
                return to_route('checkout.success', ['paypal_order_id' => $token]);
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
     * and clears the cart. Returns false when the capture did not complete
     * or the amount does not match — the callers decide how to fail.
     *
     * @param  array<string, mixed>  $response
     */
    private function completeCapture(Payment $payment, array $response): bool
    {
        $captureStatus = $response['status'] ?? 'FAILED';
        $capturedAmount = $response['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? null;

        if ($captureStatus === 'COMPLETED' && $capturedAmount !== null && number_format((float) $capturedAmount, 2, '.', '') !== number_format((float) $payment->order->total_amount, 2, '.', '')) {
            Log::error('PayPal capture amount mismatch', [
                'paypal_order_id' => $payment->paypal_order_id,
                'captured' => $capturedAmount,
                'expected' => $payment->order->total_amount,
            ]);

            // Das Geld ist bei PayPal bereits eingezogen, die Order wird aber
            // failed - ohne aktive Meldung bliebe das nur im Log haengen und
            // braeuchte Zufall, um aufzufallen (V4 B-1).
            $this->notifyAmountMismatch($payment, (string) $capturedAmount);

            return false;
        }

        if ($captureStatus !== 'COMPLETED') {
            return false;
        }

        $payerName = trim(($response['payer']['name']['given_name'] ?? '').' '.($response['payer']['name']['surname'] ?? ''));

        $payment->update([
            'status' => 'COMPLETED',
            'paypal_capture_id' => $this->payPalService->getCaptureIdFromOrder($response),
            'paypal_payer_id' => $response['payer']['payer_id'] ?? $payment->paypal_payer_id,
            'payer_email' => $response['payer']['email_address'] ?? $payment->payer_email,
            'payer_name' => $payerName !== '' ? $payerName : $payment->payer_name,
            'response_data' => $response,
        ]);

        $this->orderManager->markPaid($payment->order);
        $this->cartService->clear();

        return true;
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

            if ($current->status === 'COMPLETED') {
                return true;
            }

            $current->update(['status' => 'FAILED', 'response_data' => $responseData]);

            Order::query()
                ->whereKey($current->order_id)
                ->whereNotIn('status', ['paid', 'refunded'])
                ->update(['status' => 'failed']);

            return false;
        });
    }

    private function hasCompleted(Payment $payment): bool
    {
        return Payment::query()->whereKey($payment->getKey())->value('status') === 'COMPLETED';
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
     */
    private function handleOrderApproved(array $resource): void
    {
        $orderId = $resource['id'] ?? null;

        if (! $orderId) {
            return;
        }

        Payment::query()
            ->where('paypal_order_id', $orderId)
            ->where('status', 'CREATED')
            ->update(['status' => 'APPROVED']);
    }

    /**
     * Handle PAYMENT.CAPTURE.COMPLETED webhook.
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
        if ($payment->status === 'REFUNDED') {
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

        if ($capturedAmount !== null && number_format((float) $capturedAmount, 2, '.', '') !== number_format((float) $payment->order->total_amount, 2, '.', '')) {
            Log::error('PayPal webhook capture amount mismatch', [
                'capture_id' => $captureId,
                'captured' => $capturedAmount,
                'expected' => $payment->order->total_amount,
            ]);

            $this->notifyAmountMismatch($payment, (string) $capturedAmount);

            return;
        }

        // Bereits abgeschlossen (Normalfall: JS-Capture war schneller als der
        // Webhook): response_data nicht mit der Capture-Resource überschreiben
        // (der volle Order-Response ist der bessere Audit-Trail), nur den
        // idempotenten markPaid-Pfad sicherstellen.
        if ($payment->status !== 'COMPLETED') {
            $payment->update([
                'status' => 'COMPLETED',
                'paypal_capture_id' => $captureId,
                'response_data' => $resource,
            ]);
        }

        $this->orderManager->markPaid($payment->order);
    }

    /**
     * Handle PAYMENT.CAPTURE.REFUNDED webhook (e.g. refund issued directly
     * in the PayPal dashboard).
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
        $payment = Payment::query()->where('paypal_capture_id', $captureId)->first();

        if ($payment && $refundedAmount !== null && number_format((float) $refundedAmount, 2, '.', '') !== number_format((float) $payment->amount, 2, '.', '')) {
            Log::warning('PayPal webhook: partial refund ignored', [
                'capture_id' => $captureId,
                'refunded' => $refundedAmount,
                'captured' => $payment->amount,
            ]);

            return;
        }

        $this->markPaymentAndOrder($captureId, 'REFUNDED', 'refunded');
    }

    /**
     * Handle PAYMENT.CAPTURE.DENIED webhook.
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

        if ($order && in_array($order->status, ['paid', 'refunded'], true)) {
            Log::info('PayPal webhook: ignoring late denial for settled order', [
                'capture_id' => $captureId,
                'order_id' => $order->id,
            ]);

            return;
        }

        $this->markPaymentAndOrder($captureId, 'FAILED', 'failed');
    }

    /**
     * Update payment and its order together so webhook-driven status changes
     * (refund/denial) stay consistent with the admin refund flow.
     */
    private function markPaymentAndOrder(string $captureId, string $paymentStatus, string $orderStatus): void
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

        $payment->update(['status' => $paymentStatus]);
        $payment->order?->update(['status' => $orderStatus]);
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

    private function cancelStalePendingPayPalOrders(Customer $customer): void
    {
        Order::query()
            ->where('customer_id', $customer->id)
            ->where('payment_method', 'paypal')
            ->where('status', 'pending')
            ->whereDoesntHave('payments', fn ($q) => $q->where('status', 'COMPLETED'))
            ->update(['status' => 'cancelled']);
    }
}
