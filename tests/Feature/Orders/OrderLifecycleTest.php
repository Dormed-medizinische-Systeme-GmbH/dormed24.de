<?php

namespace Tests\Feature\Orders;

use App\Mail\PaymentConfirmationMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Orders\OrderManager;
use App\Support\Orders\OrderStatus;
use App\Support\Orders\PaymentMethod;
use App\Support\Orders\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Bestell-Lebenszyklus: welche Statusübergänge der OrderManager erlaubt.
 * Die Tabelle ist die aus .todo/architektur-plan.md (Phase 1.2).
 */
class OrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Erlaubte Übergänge: Ziel => Ausgangsstatus.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED = [
        'paid' => ['pending', 'failed', 'cancelled'],
        'failed' => ['pending', 'failed', 'cancelled'],
        'cancelled' => ['pending'],
        'refunded' => ['pending', 'paid', 'failed', 'cancelled'],
        'pending' => [],
    ];

    /**
     * @return array<string, array{OrderStatus, OrderStatus}>
     */
    public static function everyTransition(): array
    {
        $cases = [];

        foreach (OrderStatus::cases() as $from) {
            foreach (OrderStatus::cases() as $to) {
                $cases["{$from->value} -> {$to->value}"] = [$from, $to];
            }
        }

        return $cases;
    }

    #[DataProvider('everyTransition')]
    public function test_transition_follows_the_table(OrderStatus $from, OrderStatus $to): void
    {
        $order = Order::factory()->create(['status' => $from]);
        $allowed = in_array($from->value, self::ALLOWED[$to->value], true);

        $this->assertSame($allowed, $from->canTransitionTo($to));

        $changed = app(OrderManager::class)->transitionTo($order, $to);

        $this->assertSame($allowed, $changed);
        $this->assertSame($allowed ? $to : $from, $order->fresh()?->status);
    }

    public function test_only_paid_and_refunded_are_settled(): void
    {
        $settled = array_values(array_filter(OrderStatus::cases(), fn (OrderStatus $status): bool => $status->isSettled()));

        $this->assertSame([OrderStatus::Paid, OrderStatus::Refunded], $settled);
    }

    public function test_refunded_is_final(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Refunded]);

        foreach (OrderStatus::cases() as $target) {
            $this->assertFalse(app(OrderManager::class)->transitionTo($order, $target));
        }

        $this->assertSame(OrderStatus::Refunded, $order->fresh()?->status);
    }

    public function test_invoice_payment_can_only_be_confirmed_for_open_invoice_orders(): void
    {
        $manager = app(OrderManager::class);

        $openInvoice = Order::factory()->create(['status' => OrderStatus::Pending, 'payment_method' => PaymentMethod::Invoice]);
        $paidInvoice = Order::factory()->create(['status' => OrderStatus::Paid, 'payment_method' => PaymentMethod::Invoice]);
        $openPayPal = Order::factory()->create(['status' => OrderStatus::Pending, 'payment_method' => PaymentMethod::Paypal]);

        $this->assertTrue($manager->canConfirmInvoicePayment($openInvoice));
        $this->assertFalse($manager->canConfirmInvoicePayment($paidInvoice));
        $this->assertFalse($manager->canConfirmInvoicePayment($openPayPal));
    }

    public function test_confirming_a_paypal_order_as_invoice_payment_changes_nothing(): void
    {
        Mail::fake();
        $order = Order::factory()->create(['status' => OrderStatus::Pending, 'payment_method' => PaymentMethod::Paypal]);

        $this->assertFalse(app(OrderManager::class)->confirmInvoicePayment($order));

        $this->assertSame(OrderStatus::Pending, $order->fresh()?->status);
        Mail::assertNothingSent();
        Mail::assertNotSent(PaymentConfirmationMail::class);
    }

    public function test_abandoned_paypal_orders_of_the_customer_are_cancelled(): void
    {
        $customer = Customer::factory()->create();
        $other = Customer::factory()->create();

        $abandoned = Order::factory()->for($customer)->create(['status' => OrderStatus::Pending, 'payment_method' => PaymentMethod::Paypal]);
        Payment::factory()->for($abandoned)->created()->create();

        $inReview = Order::factory()->for($customer)->create(['status' => OrderStatus::Pending, 'payment_method' => PaymentMethod::Paypal]);
        Payment::factory()->for($inReview)->create(['status' => PaymentStatus::Pending]);

        $mismatch = Order::factory()->for($customer)->create(['status' => OrderStatus::Pending, 'payment_method' => PaymentMethod::Paypal]);
        Payment::factory()->for($mismatch)->create(['status' => PaymentStatus::Mismatch]);

        $invoice = Order::factory()->for($customer)->create(['status' => OrderStatus::Pending, 'payment_method' => PaymentMethod::Invoice]);
        $failed = Order::factory()->for($customer)->create(['status' => OrderStatus::Failed, 'payment_method' => PaymentMethod::Paypal]);
        $foreign = Order::factory()->for($other)->create(['status' => OrderStatus::Pending, 'payment_method' => PaymentMethod::Paypal]);

        $cancelled = app(OrderManager::class)->cancelAbandonedPayPalOrders($customer);

        $this->assertSame(1, $cancelled);
        $this->assertSame(OrderStatus::Cancelled, $abandoned->fresh()?->status);
        $this->assertSame(OrderStatus::Pending, $inReview->fresh()?->status);
        $this->assertSame(OrderStatus::Pending, $mismatch->fresh()?->status);
        $this->assertSame(OrderStatus::Pending, $invoice->fresh()?->status);
        $this->assertSame(OrderStatus::Failed, $failed->fresh()?->status);
        $this->assertSame(OrderStatus::Pending, $foreign->fresh()?->status);
    }

    public function test_only_completed_and_mismatched_payments_are_refundable(): void
    {
        $refundable = array_values(array_filter(PaymentStatus::cases(), fn (PaymentStatus $status): bool => $status->isRefundable()));

        $this->assertSame([PaymentStatus::Completed, PaymentStatus::Mismatch], $refundable);
    }

    public function test_paid_scope_only_returns_paid_orders(): void
    {
        foreach (OrderStatus::cases() as $status) {
            Order::factory()->create(['status' => $status]);
        }

        $this->assertSame([OrderStatus::Paid], Order::query()->paid()->pluck('status')->all());
    }
}
