<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cart\UpdateCartPaymentMethodRequest;
use App\Http\Requests\Checkout\PlaceOrderRequest;
use App\Models\Customer;
use App\Models\Order;
use App\Support\Address\AddressRules;
use App\Support\Cart\CartService;
use App\Support\Orders\OrderManager;
use App\Support\PaymentMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly OrderManager $orderManager,
    ) {}

    public function confirm(Request $request): Response|RedirectResponse
    {
        $this->prefillAddressFromProfile($request);

        $cart = $this->cartService->cart();

        if ($cart['is_empty']) {
            return to_route('checkout.index');
        }

        $selectedPayment = $cart['selected_payment_method'] ?? [];
        $paypalClientId = null;

        if (($selectedPayment['id'] ?? '') === 'paypal') {
            $paypalClientId = config('paypal.'.PaymentMode::current().'.client_id') ?: null;
        }

        return Inertia::render('Checkout/Confirm', [
            'cart' => $cart,
            'paypal_client_id' => $paypalClientId,
        ]);
    }

    private function prefillAddressFromProfile(Request $request): void
    {
        if (! $request->user() || $this->cartService->isAddressComplete()) {
            return;
        }

        /** @var Customer $customer */
        $customer = $request->user();

        if ($shipping = $customer->defaultShippingAddress()) {
            $this->cartService->setShippingAddress($shipping->toAddressArray());
        }

        if ($billing = $customer->defaultBillingAddress()) {
            $this->cartService->setBillingAddress($billing->toAddressArray());
        }
    }

    public function updatePayment(UpdateCartPaymentMethodRequest $request): RedirectResponse
    {
        $this->cartService->setPaymentMethod($request->string('payment_method')->toString());

        return back();
    }

    public function updateAddress(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            ...AddressRules::forPrefix('shipping_address'),
            'billing_same_as_shipping' => ['boolean'],
            ...AddressRules::forPrefix('billing_address', required: false),
        ]);

        // Save shipping address
        $this->cartService->setShippingAddress($validated['shipping_address']);

        // Save billing address (or null = same as shipping)
        if (($validated['billing_same_as_shipping'] ?? true) === true) {
            $this->cartService->setBillingAddress(null);
        } else {
            $this->cartService->setBillingAddress($validated['billing_address'] ?? []);
        }

        return back();
    }

    public function submit(PlaceOrderRequest $request): RedirectResponse
    {
        $cart = $this->cartService->cart();

        if ($cart['is_empty']) {
            return to_route('checkout.index');
        }

        if (! $this->cartService->isAddressComplete()) {
            return back()->withErrors(['shipping_address' => 'Bitte vervollständigen Sie Ihre Lieferadresse.']);
        }

        if ($this->cartService->hasUnavailableItems()) {
            return back()->withErrors(['cart' => 'Ihr Warenkorb enthält nicht mehr verfügbare Produkte. Bitte aktualisieren Sie Ihren Warenkorb.']);
        }

        // PayPal payments run through the PayPal JS SDK and PayPalController;
        // this endpoint only finalizes invoice ("Kauf auf Rechnung") orders.
        if (($cart['selected_payment_method']['id'] ?? '') !== 'invoice') {
            return back()->withErrors(['payment_method' => 'Diese Zahlungsart wird über PayPal abgeschlossen.']);
        }

        $order = $this->orderManager->createFromCart($request->user(), $cart, 'invoice');

        // Invoice stays "pending" until payment arrives by bank transfer, but the
        // customer still gets the confirmation (incl. bank details) and the admin
        // gets notified immediately.
        $this->orderManager->sendConfirmations($order);

        $this->cartService->clear();

        return to_route('checkout.success', ['order_id' => $order->id]);
    }

    public function success(Request $request): Response|RedirectResponse
    {
        $paypalOrderId = $request->query('paypal_order_id');
        $orderId = $request->query('order_id');

        $order = null;

        if ($paypalOrderId) {
            $order = Order::query()
                ->with(['items', 'customer'])
                ->where('customer_id', $request->user()?->id)
                ->whereHas('payments', fn ($q) => $q->where('paypal_order_id', $paypalOrderId))
                ->first();
        } elseif ($orderId) {
            $order = Order::query()
                ->with(['items', 'customer'])
                ->where('id', $orderId)
                ->where('customer_id', $request->user()?->id)
                ->first();
        }

        if (! $order) {
            return to_route('home');
        }

        // Eine fehlgeschlagene/stornierte Zahlung darf nie als "Vielen Dank
        // für Ihre Bestellung" enden (V4 B1) - pending ist dagegen legitim
        // (Rechnung wartet auf Überweisung).
        if (in_array($order->status, ['failed', 'cancelled'], true)) {
            return to_route('checkout.error');
        }

        $summary = $this->orderManager->summaryFromOrder($order);

        return Inertia::render('Checkout/Success', [
            'order_id' => $order->id,
            'items' => $summary['items'],
            'subtotal' => $summary['subtotal'],
            'shipping_total' => $summary['shipping_total'],
            'vat_rate' => $summary['vat_rate'],
            'vat_amount' => $summary['vat_amount'],
            'total' => $summary['total'],
            'customer_email' => $order->customer->email,
            'shipping_address' => $order->shipping_address,
            'billing_address' => $order->billing_address,
        ]);
    }
}
