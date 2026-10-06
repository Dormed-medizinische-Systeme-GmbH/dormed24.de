<?php

namespace App\Support\Orders;

use App\Mail\NewOrderMail;
use App\Mail\OrderConfirmationMail;
use App\Mail\PaymentConfirmationMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Setting;
use App\Support\Cart\Cart;
use App\Support\Cart\Money;
use App\Support\PaymentMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Central place for turning a cart into an order and for the post-payment
 * notification flow. Used by every payment method (invoice, PayPal)
 * so order creation and confirmation mails behave identically everywhere.
 */
class OrderManager
{
    /**
     * Create a pending order (incl. items) from a cart snapshot.
     */
    public function createFromCart(Customer $customer, Cart $cart, string $paymentMethod): Order
    {
        return DB::transaction(function () use ($customer, $cart, $paymentMethod): Order {
            /** @var Order $order */
            $order = Order::query()->create([
                'customer_id' => $customer->id,
                'status' => 'pending',
                'payment_method' => $paymentMethod,
                'is_test' => ! PaymentMode::isLive(),
                'total_amount' => Money::format($cart->totalCents()),
                'shipping_amount' => Money::format($cart->shippingCents()),
                'shipping_address' => $cart->shippingAddress->toArray(),
                'billing_address' => $cart->billingAddress?->toArray(),
            ]);

            foreach ($cart->items as $item) {
                $order->items()->create([
                    'product_id' => $item->productId,
                    'product_name' => $item->name,
                    'unit_price' => Money::format($item->unitPriceCents),
                    'quantity' => $item->quantity,
                ]);
            }

            return $order;
        });
    }

    /**
     * Mark an order as paid and send the confirmation mails exactly once.
     *
     * This is the "your order just came in and is paid" notification path:
     * customer confirmation *and* operator notification. Used for gateway
     * payments (PayPal), where this is the only order/payment notice either
     * of them gets. Not for the invoice manual-payment-confirmation flow -
     * see confirmInvoicePayment().
     *
     * Idempotent: returns false (and sends nothing) if the order is already paid,
     * so the PayPal capture, the PayPal return URL and the webhooks can all call
     * it without producing duplicate mails.
     */
    public function markPaid(Order $order): bool
    {
        if (! $this->transitionToPaid($order)) {
            return false;
        }

        $this->sendConfirmations($order);

        return true;
    }

    /**
     * Confirm a manual invoice payment (bank transfer/direct debit) and
     * notify the customer only, using a dedicated payment-confirmation
     * mail/template - never the generic order-confirmation mail (which
     * still asks to transfer money) and never the operator, who is the one
     * triggering this in the admin.
     *
     * Idempotent like markPaid(): returns false and sends nothing if the
     * order is already paid.
     */
    public function confirmInvoicePayment(Order $order): bool
    {
        if (! $this->transitionToPaid($order)) {
            return false;
        }

        $order->loadMissing(['customer', 'items']);
        $customer = $order->customer;

        if (! $customer) {
            Log::channel('mail')->warning('Payment confirmation skipped: no customer', ['order_id' => $order->id]);

            return true;
        }

        try {
            Mail::to($customer->email)->send(new PaymentConfirmationMail($order, $customer));
        } catch (\Throwable $e) {
            Log::channel('mail')->error('Payment confirmation mail failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }

    /**
     * Atomic transition to paid, shared by markPaid(),
     * confirmInvoicePayment() and the admin's silent payment confirmation.
     * The webhook and the PayPal return URL can both call markPaid() for the
     * same order at nearly the same time; a plain read-then-write would let
     * both pass the "already paid" check before either update lands, sending
     * confirmation mails twice.
     *
     * "refunded" is terminal: PayPal delivers webhooks at-least-once and
     * unordered, so a re-delivered CAPTURE.COMPLETED arriving after a refund
     * must never flip the order back to paid (and re-send the mails).
     * "pending" and "failed" may transition (failed = retryable capture);
     * "cancelled" too - if money actually arrives for a click-cancelled
     * PayPal order, paid is the state consistent with the money.
     */
    public function transitionToPaid(Order $order): bool
    {
        $affected = Order::query()
            ->whereKey($order->getKey())
            ->whereNotIn('status', ['paid', 'refunded'])
            ->update(['status' => 'paid']);

        if ($affected === 0) {
            return false;
        }

        $order->refresh();

        return true;
    }

    /**
     * Send the customer confirmation and the admin notification for an order.
     */
    public function sendConfirmations(Order $order): void
    {
        $order->loadMissing(['customer', 'items']);
        $customer = $order->customer;

        if (! $customer) {
            Log::channel('mail')->warning('Order confirmation skipped: no customer', ['order_id' => $order->id]);

            return;
        }

        try {
            Mail::to($customer->email)->send(new OrderConfirmationMail($order, $customer));
        } catch (\Throwable $e) {
            Log::channel('mail')->error('Order confirmation mail failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            Mail::to(Setting::get('shop.email'))->send(new NewOrderMail($order, $this->summaryFromOrder($order), $customer));
        } catch (\Throwable $e) {
            Log::channel('mail')->error('Admin order notification failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build the cart-shaped totals/items summary from a persisted order.
     *
     * Reused by the admin notification mail and the checkout success page so the
     * VAT/net maths lives in exactly one place.
     *
     * @return array{items: array<int, array<string, mixed>>, subtotal: string, shipping_total: string, vat_rate: int, vat_amount: string, total: string}
     */
    public function summaryFromOrder(Order $order): array
    {
        $order->loadMissing('items');

        $vatRate = (int) config('shop.cart.vat_rate', 19);
        $totalCents = (int) round((float) $order->total_amount * 100);
        $shippingCents = (int) round((float) $order->shipping_amount * 100);
        $subtotalCents = $totalCents - $shippingCents;
        $netTotalCents = (int) round($totalCents / (1 + ($vatRate / 100)));
        $vatAmountCents = $totalCents - $netTotalCents;

        return [
            'items' => $order->items->map(fn ($item): array => [
                'name' => $item->product_name,
                'product_number' => $item->product_id ?? '—',
                'quantity' => $item->quantity,
                'unit_price' => number_format((float) $item->unit_price, 2, '.', ''),
                'line_total' => number_format((float) $item->unit_price * $item->quantity, 2, '.', ''),
            ])->all(),
            'subtotal' => number_format($subtotalCents / 100, 2, '.', ''),
            'shipping_total' => number_format($shippingCents / 100, 2, '.', ''),
            'vat_rate' => $vatRate,
            'vat_amount' => number_format($vatAmountCents / 100, 2, '.', ''),
            'total' => number_format($totalCents / 100, 2, '.', ''),
        ];
    }
}
