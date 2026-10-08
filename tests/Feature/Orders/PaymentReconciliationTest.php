<?php

namespace Tests\Feature\Orders;

use App\Mail\NewOrderMail;
use App\Mail\OrderConfirmationMail;
use App\Mail\PaymentAmountMismatchMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Support\Orders\Gateway\CaptureResult;
use App\Support\Orders\Gateway\CreatedPayment;
use App\Support\Orders\Gateway\PaymentGateway;
use App\Support\Orders\Gateway\RefundResult;
use App\Support\Orders\Gateway\WebhookEvent;
use App\Support\Orders\Gateway\WebhookEventType;
use App\Support\Orders\OrderStatus;
use App\Support\Orders\PaymentMethod;
use App\Support\Orders\PaymentReconciliation;
use App\Support\Orders\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakePaymentGateway;
use Tests\TestCase;

/**
 * Zahlungsabgleich gegen den Fake-Adapter: welche Meldung des
 * Zahlungsdienstes Zahlung und Bestellung wie verändert, ohne PayPal-JSON.
 */
class PaymentReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private FakePaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Setting::set('shop.email', 'shop@dormed.example');

        $this->gateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);
    }

    private function reconciliation(): PaymentReconciliation
    {
        return app(PaymentReconciliation::class);
    }

    /**
     * @param  array<string, mixed>  $payment
     * @return array{Order, Payment}
     */
    private function openPayment(array $payment = [], string $total = '100.00'): array
    {
        $order = Order::factory()->create(['status' => OrderStatus::Pending, 'payment_method' => PaymentMethod::Paypal, 'total_amount' => $total]);
        $created = Payment::factory()->created()->for($order)->create(['amount' => $total, ...$payment]);

        return [$order, $created->load('order')];
    }

    /**
     * @return array{Order, Payment}
     */
    private function paidPayment(): array
    {
        $order = Order::factory()->create(['status' => OrderStatus::Paid, 'payment_method' => PaymentMethod::Paypal, 'total_amount' => '100.00']);
        $payment = Payment::factory()->completed()->for($order)->create(['paypal_capture_id' => 'CAPTURE-1', 'amount' => '100.00']);

        return [$order, $payment->load('order')];
    }

    private function completedCapture(string $amount = '100.00', string $currency = 'EUR'): CaptureResult
    {
        return new CaptureResult(
            PaymentStatus::Completed,
            captureId: 'CAPTURE-1',
            amount: $amount,
            currency: $currency,
            fee: '3.79',
            payerId: 'PAYER-1',
            payerEmail: 'kaeufer@example.com',
            payerName: 'Erika Mustermann',
        );
    }

    private function webhook(WebhookEventType $type, ?string $captureId = 'CAPTURE-1', ?string $orderReference = null, ?string $amount = null): void
    {
        $this->reconciliation()->handleWebhook(new WebhookEvent($type, $type->name, $orderReference, $captureId, $amount));
    }

    public function test_start_records_a_created_payment_with_the_order_total(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Pending, 'total_amount' => '4390.00']);
        $this->gateway->createResult = new CreatedPayment('ORDER-NEW', 'CREATED');

        $payment = $this->reconciliation()->start($order);

        $this->assertNotNull($payment);
        $this->assertSame('ORDER-NEW', $payment->paypal_order_id);
        $this->assertSame(PaymentStatus::Created, $payment->status);
        $this->assertEquals(4390.00, (float) $payment->amount);
        $this->assertSame(OrderStatus::Pending, $order->fresh()?->status);
    }

    public function test_start_without_reference_fails_the_order_and_records_nothing(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);
        $this->gateway->createResult = new CreatedPayment(null, null);

        $this->assertNull($this->reconciliation()->start($order));

        $this->assertSame(OrderStatus::Failed, $order->fresh()?->status);
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_start_exception_fails_the_order(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);
        $this->gateway->createResult = new \RuntimeException('Timeout');

        $this->assertNull($this->reconciliation()->start($order));

        $this->assertSame(OrderStatus::Failed, $order->fresh()?->status);
    }

    public function test_completed_capture_marks_payment_and_order_paid(): void
    {
        [$order, $payment] = $this->openPayment(['amount' => 0]);
        $this->gateway->captureResult = $this->completedCapture();

        $this->assertSame(PaymentStatus::Completed, $this->reconciliation()->capture($payment));

        $payment->refresh();
        $this->assertSame([$payment->paypal_order_id], $this->gateway->captured);
        $this->assertSame(PaymentStatus::Completed, $payment->status);
        $this->assertSame('CAPTURE-1', $payment->paypal_capture_id);
        $this->assertEquals(100.00, (float) $payment->amount);
        $this->assertEquals(3.79, (float) $payment->fee);
        $this->assertSame('Erika Mustermann', $payment->payer_name);
        $this->assertSame(OrderStatus::Paid, $order->fresh()?->status);
        Mail::assertSent(OrderConfirmationMail::class, 1);
        Mail::assertSent(NewOrderMail::class, 1);
    }

    public function test_capture_with_another_amount_is_a_refundable_mismatch(): void
    {
        [$order, $payment] = $this->openPayment();
        $this->gateway->captureResult = $this->completedCapture('1.00');

        $this->assertSame(PaymentStatus::Failed, $this->reconciliation()->capture($payment));

        $this->assertSame(PaymentStatus::Mismatch, $payment->fresh()?->status);
        $this->assertEquals(1.00, (float) $payment->fresh()?->amount);
        $this->assertSame(OrderStatus::Failed, $order->fresh()?->status);
        Mail::assertSent(PaymentAmountMismatchMail::class, fn ($mail): bool => $mail->hasTo('shop@dormed.example'));
        Mail::assertNotSent(OrderConfirmationMail::class);
    }

    public function test_capture_in_another_currency_is_a_mismatch(): void
    {
        [$order, $payment] = $this->openPayment();
        $this->gateway->captureResult = $this->completedCapture('100.00', 'USD');

        $this->assertSame(PaymentStatus::Failed, $this->reconciliation()->capture($payment));

        $this->assertSame(PaymentStatus::Mismatch, $payment->fresh()?->status);
        $this->assertSame(OrderStatus::Failed, $order->fresh()?->status);
    }

    public function test_pending_capture_keeps_the_order_open(): void
    {
        [$order, $payment] = $this->openPayment();
        $this->gateway->captureResult = new CaptureResult(PaymentStatus::Pending, captureId: 'CAPTURE-REVIEW');

        $this->assertSame(PaymentStatus::Pending, $this->reconciliation()->capture($payment));

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()?->status);
        $this->assertSame('CAPTURE-REVIEW', $payment->fresh()?->paypal_capture_id);
        $this->assertSame(OrderStatus::Pending, $order->fresh()?->status);
    }

    public function test_declined_capture_fails_payment_and_order(): void
    {
        [$order, $payment] = $this->openPayment();
        $this->gateway->captureResult = new CaptureResult(PaymentStatus::Failed);

        $this->assertSame(PaymentStatus::Failed, $this->reconciliation()->capture($payment));

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()?->status);
        $this->assertSame(OrderStatus::Failed, $order->fresh()?->status);
    }

    public function test_failed_capture_counts_as_completed_when_the_webhook_was_faster(): void
    {
        [$order, $payment] = $this->openPayment();
        $this->gateway->captureResult = function () use ($payment): CaptureResult {
            $this->webhook(WebhookEventType::CaptureCompleted, 'CAPTURE-WEBHOOK', $payment->paypal_order_id, '100.00');

            return new CaptureResult(PaymentStatus::Failed);
        };

        $this->assertSame(PaymentStatus::Completed, $this->reconciliation()->capture($payment));

        $this->assertSame(PaymentStatus::Completed, $payment->fresh()?->status);
        $this->assertSame(OrderStatus::Paid, $order->fresh()?->status);
    }

    public function test_capture_exception_fails_the_payment_and_is_rethrown(): void
    {
        [$order, $payment] = $this->openPayment();
        $this->gateway->captureResult = new \RuntimeException('Timeout');

        try {
            $this->reconciliation()->capture($payment);
            $this->fail('Exception erwartet');
        } catch (\RuntimeException $e) {
            $this->assertSame('Timeout', $e->getMessage());
        }

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()?->status);
        $this->assertSame(OrderStatus::Failed, $order->fresh()?->status);
    }

    public function test_refund_refunds_the_captured_amount(): void
    {
        [$order, $payment] = $this->paidPayment();

        $this->assertSame(PaymentStatus::Refunded, $this->reconciliation()->refund($payment));

        $this->assertSame([['captureId' => 'CAPTURE-1', 'amount' => 100.0, 'currency' => 'EUR']], $this->gateway->refunds);
        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()?->status);
        $this->assertSame(OrderStatus::Refunded, $order->fresh()?->status);
    }

    /**
     * @return array<string, array{RefundResult|\Throwable, PaymentStatus}>
     */
    public static function unconfirmedRefunds(): array
    {
        return [
            'pending' => [new RefundResult(PaymentStatus::Pending), PaymentStatus::Pending],
            'failed' => [new RefundResult(PaymentStatus::Failed), PaymentStatus::Failed],
            'exception' => [new \RuntimeException('Timeout'), PaymentStatus::Failed],
        ];
    }

    #[DataProvider('unconfirmedRefunds')]
    public function test_unconfirmed_refund_changes_nothing(RefundResult|\Throwable $result, PaymentStatus $expected): void
    {
        [$order, $payment] = $this->paidPayment();
        $this->gateway->refundResult = $result;

        $this->assertSame($expected, $this->reconciliation()->refund($payment));

        $this->assertSame(PaymentStatus::Completed, $payment->fresh()?->status);
        $this->assertSame(OrderStatus::Paid, $order->fresh()?->status);
    }

    public function test_approved_webhook_marks_a_created_payment_approved(): void
    {
        [, $payment] = $this->openPayment();

        $this->webhook(WebhookEventType::PaymentApproved, null, $payment->paypal_order_id);

        $this->assertSame(PaymentStatus::Approved, $payment->fresh()?->status);
    }

    public function test_redelivered_capture_webhook_marks_paid_and_mails_once(): void
    {
        [$order, $payment] = $this->openPayment(['paypal_capture_id' => 'CAPTURE-1']);

        $this->webhook(WebhookEventType::CaptureCompleted, amount: '100.00');
        $this->webhook(WebhookEventType::CaptureCompleted, amount: '100.00');

        $this->assertSame(PaymentStatus::Completed, $payment->fresh()?->status);
        $this->assertSame(OrderStatus::Paid, $order->fresh()?->status);
        Mail::assertSent(OrderConfirmationMail::class, 1);
    }

    public function test_capture_webhook_finds_the_payment_by_order_reference(): void
    {
        [$order, $payment] = $this->openPayment();

        $this->webhook(WebhookEventType::CaptureCompleted, 'CAPTURE-NEW', $payment->paypal_order_id, '100.00');

        $this->assertSame('CAPTURE-NEW', $payment->fresh()?->paypal_capture_id);
        $this->assertSame(OrderStatus::Paid, $order->fresh()?->status);
    }

    public function test_late_capture_webhook_does_not_undo_a_refund(): void
    {
        [$order, $payment] = $this->paidPayment();
        $payment->update(['status' => PaymentStatus::Refunded]);
        $order->update(['status' => OrderStatus::Refunded]);

        $this->webhook(WebhookEventType::CaptureCompleted, amount: '100.00');

        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()?->status);
        $this->assertSame(OrderStatus::Refunded, $order->fresh()?->status);
    }

    public function test_capture_webhook_with_another_amount_is_a_mismatch(): void
    {
        [$order, $payment] = $this->openPayment(['paypal_capture_id' => 'CAPTURE-1']);

        $this->webhook(WebhookEventType::CaptureCompleted, amount: '1.00');

        $this->assertSame(PaymentStatus::Mismatch, $payment->fresh()?->status);
        $this->assertSame(OrderStatus::Failed, $order->fresh()?->status);
        Mail::assertSent(PaymentAmountMismatchMail::class);
    }

    public function test_pending_webhook_marks_the_payment_in_review(): void
    {
        [$order, $payment] = $this->openPayment();

        $this->webhook(WebhookEventType::CapturePending, 'CAPTURE-REVIEW', $payment->paypal_order_id);

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()?->status);
        $this->assertSame(OrderStatus::Pending, $order->fresh()?->status);
    }

    public function test_denied_webhook_fails_an_open_order(): void
    {
        [$order, $payment] = $this->openPayment(['paypal_capture_id' => 'CAPTURE-1']);

        $this->webhook(WebhookEventType::CaptureDenied);

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()?->status);
        $this->assertSame(OrderStatus::Failed, $order->fresh()?->status);
    }

    public function test_late_denied_webhook_does_not_fail_a_paid_order(): void
    {
        [$order, $payment] = $this->paidPayment();

        $this->webhook(WebhookEventType::CaptureDenied);

        $this->assertSame(PaymentStatus::Completed, $payment->fresh()?->status);
        $this->assertSame(OrderStatus::Paid, $order->fresh()?->status);
    }

    public function test_full_refund_webhook_refunds_payment_and_order(): void
    {
        [$order, $payment] = $this->paidPayment();

        $this->webhook(WebhookEventType::CaptureRefunded, amount: '100.00');

        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()?->status);
        $this->assertSame(OrderStatus::Refunded, $order->fresh()?->status);
    }

    public function test_partial_refund_webhook_changes_nothing(): void
    {
        [$order, $payment] = $this->paidPayment();

        $this->webhook(WebhookEventType::CaptureRefunded, amount: '10.00');

        $this->assertSame(PaymentStatus::Completed, $payment->fresh()?->status);
        $this->assertSame(OrderStatus::Paid, $order->fresh()?->status);
    }

    public function test_reversed_webhook_counts_as_refund(): void
    {
        [$order, $payment] = $this->paidPayment();

        $this->webhook(WebhookEventType::CaptureReversed);

        $this->assertSame(PaymentStatus::Reversed, $payment->fresh()?->status);
        $this->assertSame(OrderStatus::Refunded, $order->fresh()?->status);
    }

    public function test_webhook_without_reference_changes_nothing(): void
    {
        [$order, $payment] = $this->paidPayment();

        foreach (WebhookEventType::cases() as $type) {
            $this->webhook($type, null);
        }

        $this->assertSame(PaymentStatus::Completed, $payment->fresh()?->status);
        $this->assertSame(OrderStatus::Paid, $order->fresh()?->status);
    }

    /**
     * Der Controller hängt nur am Seam: Mit dem Fake statt PayPal laufen
     * Webhooks genauso durch.
     */
    public function test_webhook_route_works_with_any_gateway(): void
    {
        [$order, $payment] = $this->openPayment(['paypal_capture_id' => 'CAPTURE-1']);

        $this->gateway->webhookVerified = false;
        $this->postJson(route('paypal.webhook'), ['type' => 'CaptureCompleted', 'captureId' => 'CAPTURE-1', 'amount' => '100.00'])
            ->assertStatus(400);
        $this->assertSame(OrderStatus::Pending, $order->fresh()?->status);

        $this->gateway->webhookVerified = true;
        $this->postJson(route('paypal.webhook'), ['type' => 'CaptureCompleted', 'captureId' => 'CAPTURE-1', 'amount' => '100.00'])
            ->assertOk();
        $this->assertSame(PaymentStatus::Completed, $payment->fresh()?->status);
        $this->assertSame(OrderStatus::Paid, $order->fresh()?->status);
    }
}
