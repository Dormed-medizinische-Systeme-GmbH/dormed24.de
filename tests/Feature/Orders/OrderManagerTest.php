<?php

namespace Tests\Feature\Orders;

use App\Mail\NewOrderMail;
use App\Mail\OrderConfirmationMail;
use App\Mail\PaymentConfirmationMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Support\Cart\Cart;
use App\Support\Cart\CartAddress;
use App\Support\Cart\CartItem;
use App\Support\Cart\PaymentOption;
use App\Support\Cart\ShippingOption;
use App\Support\Orders\OrderManager;
use App\Support\Orders\OrderStatus;
use App\Support\Orders\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Mail;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class OrderManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_paid_transitions_pending_order_and_sends_confirmations(): void
    {
        Mail::fake();

        $order = Order::factory()->create(['status' => 'pending']);

        $result = app(OrderManager::class)->markPaid($order);

        $this->assertTrue($result);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        Mail::assertSent(OrderConfirmationMail::class);
        Mail::assertSent(NewOrderMail::class);
    }

    public function test_mark_paid_is_idempotent_and_sends_confirmations_only_once(): void
    {
        Mail::fake();

        $order = Order::factory()->create(['status' => 'pending']);
        $manager = app(OrderManager::class);

        $first = $manager->markPaid($order);
        $second = $manager->markPaid($order->fresh());

        $this->assertTrue($first);
        $this->assertFalse($second);
        Mail::assertSent(OrderConfirmationMail::class, 1);
        Mail::assertSent(NewOrderMail::class, 1);
    }

    public function test_mark_paid_refuses_refunded_orders(): void
    {
        Mail::fake();

        $order = Order::factory()->create(['status' => 'refunded']);

        $result = app(OrderManager::class)->markPaid($order);

        $this->assertFalse($result);
        $this->assertSame(OrderStatus::Refunded, $order->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_mark_paid_allows_retry_after_failed_capture(): void
    {
        Mail::fake();

        $order = Order::factory()->create(['status' => 'failed']);

        $result = app(OrderManager::class)->markPaid($order);

        $this->assertTrue($result);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_confirm_invoice_payment_transitions_order_and_notifies_customer_only(): void
    {
        Mail::fake();

        $order = Order::factory()->create(['status' => 'pending', 'payment_method' => 'invoice']);

        $result = app(OrderManager::class)->confirmInvoicePayment($order);

        $this->assertTrue($result);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        Mail::assertSent(PaymentConfirmationMail::class);
        Mail::assertNotSent(OrderConfirmationMail::class);
        Mail::assertNotSent(NewOrderMail::class);
    }

    public function test_confirm_invoice_payment_is_idempotent_and_notifies_only_once(): void
    {
        Mail::fake();

        $order = Order::factory()->create(['status' => 'pending', 'payment_method' => 'invoice']);
        $manager = app(OrderManager::class);

        $first = $manager->confirmInvoicePayment($order);
        $second = $manager->confirmInvoicePayment($order->fresh());

        $this->assertTrue($first);
        $this->assertFalse($second);
        Mail::assertSent(PaymentConfirmationMail::class, 1);
    }

    public function test_create_from_cart_rolls_back_order_when_an_item_fails(): void
    {
        $customer = Customer::factory()->create();

        // Der Item-Insert schlägt fehl, die bereits angelegte Order darf
        // nicht stehen bleiben.
        OrderItem::creating(fn () => throw new RuntimeException('Item-Insert fehlgeschlagen'));

        try {
            app(OrderManager::class)->createFromCart($customer, $this->cartWithOneItem(), PaymentMethod::Invoice);
            $this->fail('Expected the item insert to fail.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_create_from_cart_persists_totals_addresses_and_items(): void
    {
        $customer = Customer::factory()->create();

        $order = app(OrderManager::class)->createFromCart($customer, $this->cartWithOneItem(), PaymentMethod::Invoice);

        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(PaymentMethod::Invoice, $order->payment_method);
        $this->assertSame('44.88', $order->total_amount);
        $this->assertSame('4.90', $order->shipping_amount);
        $this->assertSame('Berlin', $order->shipping_address['city']);
        $this->assertNull($order->billing_address);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => 7,
            'product_name' => 'Ultraschallgel',
            'unit_price' => '19.99',
            'quantity' => 2,
        ]);
    }

    /**
     * 2 × 19,99 + 4,90 Versand = 44,88.
     */
    private function cartWithOneItem(): Cart
    {
        return new Cart(
            items: [
                new CartItem(
                    lineKey: '7',
                    productId: 7,
                    variantId: null,
                    variantLabel: null,
                    name: 'Ultraschallgel',
                    description: null,
                    productNumber: '7',
                    manufacturerName: null,
                    quantity: 2,
                    unitPriceCents: 1999,
                    imageUrl: null,
                    productUrl: 'https://example.com/produkte/7',
                    isAvailable: true,
                ),
            ],
            vatRate: 19,
            shippingMethods: [new ShippingOption('1', 'Standard', null, 490, true)],
            paymentMethods: [new PaymentOption('invoice', 'invoice', 'Kauf auf Rechnung', null, true)],
            shippingAddress: CartAddress::fromArray(['first_name' => 'Erika', 'last_name' => 'Muster', 'city' => 'Berlin']),
            billingAddress: null,
        );
    }

    private function orderWithoutCustomer(): Order
    {
        $order = Order::factory()->create(['status' => 'pending', 'payment_method' => 'invoice']);
        // Kunde gelöscht: Bestellungen bleiben als Snapshot erhalten.
        $order->customer()->delete();

        return $order->fresh();
    }

    public function test_mark_paid_without_customer_skips_mails_but_still_marks_paid(): void
    {
        Mail::fake();
        $order = $this->orderWithoutCustomer();
        $this->assertNull($order->customer_id);

        $this->assertTrue(app(OrderManager::class)->markPaid($order));

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_confirm_invoice_payment_without_customer_skips_mail_but_still_marks_paid(): void
    {
        Mail::fake();
        $order = $this->orderWithoutCustomer();

        $this->assertTrue(app(OrderManager::class)->confirmInvoicePayment($order));

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_failing_customer_mail_still_notifies_the_shop_and_keeps_the_order_paid(): void
    {
        Setting::set('shop.email', 'shop@dormed.example');
        $order = Order::factory()->create(['status' => 'pending']);
        $customerEmail = $order->customer->email;
        $sentToShop = [];

        Mail::shouldReceive('to')->andReturnUsing(function (string $address) use ($customerEmail, &$sentToShop) {
            $pending = Mockery::mock(PendingMail::class);
            $pending->shouldReceive('send')->andReturnUsing(function ($mailable) use ($address, $customerEmail, &$sentToShop) {
                if ($address === $customerEmail) {
                    throw new RuntimeException('SMTP down');
                }

                $sentToShop[] = $mailable::class;

                return null;
            });

            return $pending;
        });

        $this->assertTrue(app(OrderManager::class)->markPaid($order));

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame([NewOrderMail::class], $sentToShop);
    }

    public function test_failing_payment_confirmation_mail_does_not_undo_the_payment(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP down'));
        $order = Order::factory()->create(['status' => 'pending', 'payment_method' => 'invoice']);

        $this->assertTrue(app(OrderManager::class)->confirmInvoicePayment($order));

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }
}
