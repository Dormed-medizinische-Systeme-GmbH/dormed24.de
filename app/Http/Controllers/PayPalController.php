<?php

namespace App\Http\Controllers;

use App\Http\Requests\Checkout\PlaceOrderRequest;
use App\Models\Payment;
use App\Support\Cart\CartService;
use App\Support\Orders\Gateway\PaymentGateway;
use App\Support\Orders\OrderManager;
use App\Support\Orders\PaymentMethod;
use App\Support\Orders\PaymentReconciliation;
use App\Support\Orders\PaymentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * HTTP-Seite der PayPal-Zahlung: Anfragen annehmen, Eigentum prüfen und
 * Antworten bauen. Was mit Zahlung und Bestellung passiert, entscheidet der
 * Zahlungsabgleich (PaymentReconciliation).
 */
class PayPalController extends Controller
{
    private const PENDING_MESSAGE = 'Ihre Zahlung wird von PayPal noch geprüft. Sie erhalten eine Bestätigung per E-Mail, sobald sie abgeschlossen ist. Bitte zahlen Sie nicht erneut.';

    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly PaymentReconciliation $reconciliation,
        private readonly CartService $cartService,
        private readonly OrderManager $orderManager,
    ) {}

    /**
     * Schritt 1: Bestellung anlegen und die Zahlung bei PayPal eröffnen.
     * Der PayPal-Button startet mit der gelieferten ID die Freigabe.
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

        // Jeder Klick auf den PayPal-Button legt eine neue Bestellung an;
        // abgebrochene Versuche davor werden storniert, damit sich keine
        // verwaisten Bestellungen ansammeln.
        $this->orderManager->cancelAbandonedPayPalOrders($request->user());

        $order = $this->orderManager->createFromCart($request->user(), $cart, PaymentMethod::Paypal);

        // Der Warenkorb bleibt bewusst bis zum Capture bestehen, damit eine
        // abgebrochene Freigabe ihn nicht leert.
        $payment = $this->reconciliation->start($order);

        if (! $payment) {
            return response()->json(['error' => 'PayPal-Order konnte nicht erstellt werden.'], 500);
        }

        return response()->json(['id' => $payment->paypal_order_id, 'status' => $payment->status]);
    }

    /**
     * Schritt 2: Einzug nach der Freigabe, aufgerufen vom PayPal-Button.
     */
    public function captureOrder(Request $request): JsonResponse
    {
        $paypalOrderId = (string) $request->validate(['paypal_order_id' => 'required|string'])['paypal_order_id'];
        $payment = $this->ownPayment($request, $paypalOrderId);

        if (! $payment) {
            return response()->json(['error' => 'Zahlung nicht gefunden.'], 404);
        }

        if ($payment->status === PaymentStatus::Completed) {
            return response()->json(['error' => 'Zahlung bereits abgeschlossen.'], 409);
        }

        try {
            $status = $this->capture($payment);
        } catch (\Throwable $e) {
            Log::error('PayPal captureOrder exception', ['paypal_order_id' => $paypalOrderId, 'error' => $e->getMessage()]);

            return response()->json(['error' => 'Zahlung konnte nicht abgeschlossen werden.'], 500);
        }

        // Nicht abgeschlossen antwortet mit 422, damit der PayPal-Button den
        // Fehlerpfad nimmt, statt auf die Erfolgsseite weiterzuleiten.
        return match ($status) {
            PaymentStatus::Completed => response()->json(['status' => 'COMPLETED', 'paypal_order_id' => $paypalOrderId]),
            PaymentStatus::Pending => response()->json(['status' => 'PENDING', 'paypal_order_id' => $paypalOrderId, 'error' => self::PENDING_MESSAGE], 422),
            default => response()->json([
                'status' => 'FAILED',
                'paypal_order_id' => $paypalOrderId,
                'error' => 'Die Zahlung wurde von PayPal nicht abgeschlossen. Bitte versuchen Sie es erneut oder wählen Sie eine andere Zahlungsart.',
            ], 422),
        };
    }

    /**
     * Rückkehr von PayPal (Return-URL): zieht die Zahlung ein, falls der
     * PayPal-Button das noch nicht getan hat.
     */
    public function afterPayment(Request $request): RedirectResponse
    {
        $token = $request->string('token')->toString();
        $payment = $token !== '' ? $this->ownPayment($request, $token) : null;

        if (! $payment) {
            return to_route('checkout.index');
        }

        if ($payment->status === PaymentStatus::Completed) {
            return to_route('checkout.success', ['paypal_order_id' => $token]);
        }

        try {
            $status = $this->capture($payment);
        } catch (\Throwable $e) {
            Log::error('PayPal afterPayment capture failed', ['token' => $token, 'error' => $e->getMessage()]);

            return to_route('checkout.error');
        }

        return match ($status) {
            PaymentStatus::Completed => to_route('checkout.success', ['paypal_order_id' => $token]),
            PaymentStatus::Pending => to_route('checkout.index')->with('error', self::PENDING_MESSAGE),
            default => to_route('checkout.error'),
        };
    }

    /**
     * Webhook von PayPal. Die Signaturprüfung ist die einzige
     * Authentifizierung dieses Endpunkts.
     */
    public function webhook(Request $request): Response
    {
        if (! $this->gateway->verifyWebhook($request)) {
            Log::warning('PayPal webhook verification failed', ['headers' => $request->headers->all()]);

            return response('Verification failed', 400);
        }

        $this->reconciliation->handleWebhook($this->gateway->parseWebhook($request->all()));

        return response('', 200);
    }

    /**
     * Zahlung zur PayPal-Order, nur wenn sie dem eingeloggten Kunden gehört.
     * Sonst könnte ein Dritter mit bekanntem Token fremde Zahlungen einziehen
     * und dabei den Warenkorb seiner eigenen Session leeren.
     */
    private function ownPayment(Request $request, string $paypalOrderId): ?Payment
    {
        return Payment::query()
            ->where('paypal_order_id', $paypalOrderId)
            ->whereHas('order', fn ($query) => $query->where('customer_id', $request->user()?->id))
            ->with('order')
            ->first();
    }

    /**
     * Einzug über den Abgleich; ist die Zahlung abgeschlossen, wird der
     * Warenkorb geleert.
     *
     * @throws \Throwable
     */
    private function capture(Payment $payment): PaymentStatus
    {
        $status = $this->reconciliation->capture($payment);

        if ($status === PaymentStatus::Completed) {
            $this->cartService->clear();
        }

        return $status;
    }
}
