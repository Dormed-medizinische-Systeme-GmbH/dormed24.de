<?php

namespace App\Support\Orders;

use App\Mail\NewOrderMail;
use App\Mail\OrderConfirmationMail;
use App\Mail\PaymentConfirmationMail;
use App\Models\Customer;
use App\Models\Order;
use App\Support\Cart\Cart;
use App\Support\Cart\Money;
use App\Support\PaymentMode;
use App\Support\ShopContact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Central place for turning a cart into an order, for every order-status
 * transition and for the post-payment notification flow. Used by every
 * payment method (invoice, PayPal) so order creation, status rules and
 * confirmation mails behave identically everywhere. Which transitions are
 * allowed is defined in OrderStatus::reachableFrom().
 */
class OrderManager
{
    /**
     * Create a pending order (incl. items) from a cart snapshot.
     */
    public function createFromCart(Customer $customer, Cart $cart, PaymentMethod $paymentMethod): Order
    {
        return DB::transaction(function () use ($customer, $cart, $paymentMethod): Order {
            /** @var Order $order */
            $order = Order::query()->create([
                'customer_id' => $customer->id,
                'status' => OrderStatus::Pending,
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
        if (! $this->transitionTo($order, OrderStatus::Paid)) {
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
        if (! $this->canConfirmInvoicePayment($order) || ! $this->transitionTo($order, OrderStatus::Paid)) {
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
     * Zahlungseingang bestätigen geht nur für offene Rechnungsbestellungen.
     * PayPal-Bestellungen steuern Capture und Webhook allein.
     */
    public function canConfirmInvoicePayment(Order $order): bool
    {
        return $order->payment_method === PaymentMethod::Invoice
            && $order->status === OrderStatus::Pending;
    }

    /**
     * Atomic status transition, allowed only from the states listed in
     * OrderStatus::reachableFrom(). Returns false (and changes nothing) if
     * the order is not in one of them, e.g. already paid or refunded.
     *
     * Atomic because the webhook and the PayPal return URL can both call
     * markPaid() for the same order at nearly the same time; a plain
     * read-then-write would let both pass the check before either update
     * lands, sending confirmation mails twice. PayPal also delivers webhooks
     * at-least-once and unordered, so a late CAPTURE.COMPLETED after a
     * refund must not flip the order back to paid.
     */
    public function transitionTo(Order $order, OrderStatus $target): bool
    {
        $affected = Order::query()
            ->whereKey($order->getKey())
            ->whereIn('status', $target->reachableFrom())
            ->update(['status' => $target]);

        if ($affected === 0) {
            return false;
        }

        $order->refresh();

        return true;
    }

    /**
     * Storniert abgebrochene PayPal-Versuche eines Kunden: offen und ohne
     * Zahlung, die eingezogen ist, noch geprüft wird oder mit Abweichung
     * eingegangen ist.
     */
    public function cancelAbandonedPayPalOrders(Customer $customer): int
    {
        return Order::query()
            ->where('customer_id', $customer->id)
            ->where('payment_method', PaymentMethod::Paypal)
            ->whereIn('status', OrderStatus::Cancelled->reachableFrom())
            ->whereDoesntHave('payments', fn ($query) => $query->whereIn('status', [
                PaymentStatus::Completed,
                PaymentStatus::Pending,
                PaymentStatus::Mismatch,
            ]))
            ->update(['status' => OrderStatus::Cancelled]);
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
            Mail::to(app(ShopContact::class)->email())->send(new NewOrderMail($order, OrderSummary::fromOrder($order), $customer));
        } catch (\Throwable $e) {
            Log::channel('mail')->error('Admin order notification failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
