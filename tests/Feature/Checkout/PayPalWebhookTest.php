<?php

namespace Tests\Feature\Checkout;

use App\Mail\NewOrderMail;
use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PayPalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\PayPalFixtures;
use Tests\TestCase;

class PayPalWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function withVerifiedWebhook(): void
    {
        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldReceive('verifyWebhook')->once()->andReturn(true);
        });
    }

    /**
     * PAYMENT.CAPTURE.REFUNDED liefert die Refund-Resource: "id" ist die
     * Refund-ID, die Capture-ID steht im up-Link (Fixture: links[1]).
     *
     * @return array<string, mixed>
     */
    private function refundEvent(string $captureId, string $refundedAmount): array
    {
        return PayPalFixtures::webhookEvent('PAYMENT.CAPTURE.REFUNDED', 'webhooks/refund', [
            'amount' => ['value' => $refundedAmount],
            'links' => [1 => ['href' => "https://api.sandbox.paypal.com/v2/payments/captures/{$captureId}"]],
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function captureCompletedEvent(string $captureId, string $amount, array $overrides = []): array
    {
        return PayPalFixtures::webhookEvent('PAYMENT.CAPTURE.COMPLETED', 'webhooks/capture', array_replace_recursive([
            'id' => $captureId,
            'amount' => ['value' => $amount],
            'seller_receivable_breakdown' => ['gross_amount' => ['value' => $amount]],
        ], $overrides));
    }

    public function test_webhook_rejects_unverified_requests(): void
    {
        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldReceive('verifyWebhook')->once()->andReturn(false);
        });

        $this->postJson(route('paypal.webhook'), PayPalFixtures::webhookEvent('PAYMENT.CAPTURE.COMPLETED', 'webhooks/capture'))
            ->assertStatus(400);
    }

    public function test_refund_webhook_updates_payment_and_order(): void
    {
        $order = Order::factory()->create(['status' => 'paid', 'payment_method' => 'paypal']);
        $payment = Payment::factory()->completed()->for($order)->create();

        $this->withVerifiedWebhook();

        $this->postJson(route('paypal.webhook'), $this->refundEvent($payment->paypal_capture_id, $payment->amount))
            ->assertOk();

        $this->assertSame('REFUNDED', $payment->fresh()->status);
        $this->assertSame('refunded', $order->fresh()->status);
    }

    public function test_denied_webhook_updates_payment_and_order(): void
    {
        $order = Order::factory()->create(['status' => 'pending', 'payment_method' => 'paypal']);
        $payment = Payment::factory()->completed()->for($order)->create();

        $this->withVerifiedWebhook();

        // PAYMENT.CAPTURE.DENIED liefert die Capture selbst.
        $this->postJson(route('paypal.webhook'), PayPalFixtures::webhookEvent('PAYMENT.CAPTURE.DENIED', 'webhooks/capture-denied', [
            'id' => $payment->paypal_capture_id,
        ]))->assertOk();

        $this->assertSame('FAILED', $payment->fresh()->status);
        $this->assertSame('failed', $order->fresh()->status);
    }

    public function test_refund_webhook_for_unknown_capture_changes_nothing(): void
    {
        $order = Order::factory()->create(['status' => 'paid', 'payment_method' => 'paypal']);
        $payment = Payment::factory()->completed()->for($order)->create();

        $this->withVerifiedWebhook();

        $this->postJson(route('paypal.webhook'), $this->refundEvent('UNKNOWNCAPTURE', $payment->amount))
            ->assertOk();

        $this->assertSame('COMPLETED', $payment->fresh()->status);
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_late_capture_completed_webhook_does_not_revert_a_refund(): void
    {
        Mail::fake();

        $order = Order::factory()->create(['status' => 'refunded', 'payment_method' => 'paypal']);
        $payment = Payment::factory()->completed()->for($order)->create(['status' => 'REFUNDED']);

        $this->withVerifiedWebhook();

        // PayPal stellt Webhooks at-least-once und unsortiert zu: ein
        // CAPTURE.COMPLETED kann nach dem Refund (erneut) eintreffen.
        $this->postJson(route('paypal.webhook'), $this->captureCompletedEvent($payment->paypal_capture_id, $order->total_amount))
            ->assertOk();

        $this->assertSame('REFUNDED', $payment->fresh()->status);
        $this->assertSame('refunded', $order->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_capture_completed_webhook_marks_pending_order_paid(): void
    {
        $order = Order::factory()->create(['status' => 'pending', 'payment_method' => 'paypal']);
        $payment = Payment::factory()->for($order)->create([
            'status' => 'APPROVED',
            'paypal_capture_id' => 'CAPTURE555',
            'amount' => $order->total_amount,
        ]);

        $this->withVerifiedWebhook();

        $this->postJson(route('paypal.webhook'), $this->captureCompletedEvent('CAPTURE555', $order->total_amount))
            ->assertOk();

        $this->assertSame('COMPLETED', $payment->fresh()->status);
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_capture_completed_webhook_stores_captured_amount_and_fee(): void
    {
        $order = Order::factory()->create(['status' => 'pending', 'payment_method' => 'paypal', 'total_amount' => '4390.00']);
        $payment = Payment::factory()->for($order)->create([
            'status' => 'APPROVED',
            'paypal_capture_id' => 'CAPTURE-WEBHOOK-AMOUNT',
            'amount' => 0,
            'fee' => null,
        ]);

        $this->withVerifiedWebhook();

        // Echte Capture-Resource: 4.390,00 €, Gebühr 153,99 €.
        $this->postJson(route('paypal.webhook'), $this->captureCompletedEvent('CAPTURE-WEBHOOK-AMOUNT', '4390.00'))
            ->assertOk();

        $payment->refresh();
        $this->assertSame('COMPLETED', $payment->status);
        $this->assertEquals(4390.00, (float) $payment->amount);
        $this->assertEquals(153.99, (float) $payment->fee);
    }

    /**
     * Zahlungen von vor dem Betrags-Fix haben amount = 0. Eine volle
     * Erstattung darf dann nicht als Teilerstattung ignoriert werden.
     */
    public function test_full_refund_webhook_is_applied_when_payment_amount_is_missing(): void
    {
        $order = Order::factory()->create(['status' => 'paid', 'payment_method' => 'paypal', 'total_amount' => '4390.00']);
        $payment = Payment::factory()->completed()->for($order)->create(['amount' => 0]);

        $this->withVerifiedWebhook();

        $this->postJson(route('paypal.webhook'), $this->refundEvent($payment->paypal_capture_id, '4390.00'))
            ->assertOk();

        $this->assertSame('REFUNDED', $payment->fresh()->status);
        $this->assertSame('refunded', $order->fresh()->status);
    }

    public function test_capture_completed_webhook_rejects_amount_mismatch(): void
    {
        $order = Order::factory()->create([
            'status' => 'pending',
            'payment_method' => 'paypal',
            'total_amount' => '100.00',
        ]);
        $payment = Payment::factory()->for($order)->create([
            'status' => 'APPROVED',
            'paypal_capture_id' => 'CAPTURE777',
        ]);

        $this->withVerifiedWebhook();

        $this->postJson(route('paypal.webhook'), $this->captureCompletedEvent('CAPTURE777', '1.00'))
            ->assertOk();

        $this->assertSame('MISMATCH', $payment->fresh()->status);
        $this->assertSame('failed', $order->fresh()->status);
    }

    public function test_capture_pending_webhook_marks_payment_in_review_and_keeps_the_order_open(): void
    {
        $order = Order::factory()->create(['status' => 'pending', 'payment_method' => 'paypal']);
        $payment = Payment::factory()->for($order)->create(['status' => 'APPROVED', 'paypal_order_id' => '5R197099C6919743A']);
        $this->withVerifiedWebhook();

        $this->postJson(route('paypal.webhook'), PayPalFixtures::webhookEvent('PAYMENT.CAPTURE.PENDING', 'webhooks/capture', [
            'id' => 'CAPTURE-IN-REVIEW',
            'status' => 'PENDING',
        ]))->assertOk();

        $this->assertSame('PENDING', $payment->fresh()->status);
        $this->assertSame('CAPTURE-IN-REVIEW', $payment->fresh()->paypal_capture_id);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_capture_reversed_webhook_marks_payment_reversed_and_order_refunded(): void
    {
        $order = Order::factory()->create(['status' => 'paid', 'payment_method' => 'paypal']);
        $payment = Payment::factory()->completed()->for($order)->create(['paypal_capture_id' => 'CAPTURE-REVERSED']);
        $this->withVerifiedWebhook();

        $event = $this->refundEvent('CAPTURE-REVERSED', (string) $payment->amount);
        $event['event_type'] = 'PAYMENT.CAPTURE.REVERSED';

        $this->postJson(route('paypal.webhook'), $event)->assertOk();

        $this->assertSame('REVERSED', $payment->fresh()->status);
        $this->assertSame('refunded', $order->fresh()->status);
    }

    public function test_order_approved_webhook_marks_created_payment_as_approved(): void
    {
        $payment = Payment::factory()->created()->create(['paypal_order_id' => 'ORDER-APPROVE-1']);

        $this->withVerifiedWebhook();

        $this->postJson(route('paypal.webhook'), PayPalFixtures::webhookEvent('CHECKOUT.ORDER.APPROVED', 'webhooks/order-approved', [
            'id' => 'ORDER-APPROVE-1',
        ]))->assertOk();

        $this->assertSame('APPROVED', $payment->fresh()->status);
    }

    public function test_order_approved_webhook_does_not_downgrade_a_completed_payment(): void
    {
        $payment = Payment::factory()->completed()->create(['paypal_order_id' => 'ORDER-APPROVE-2']);

        $this->withVerifiedWebhook();

        $this->postJson(route('paypal.webhook'), PayPalFixtures::webhookEvent('CHECKOUT.ORDER.APPROVED', 'webhooks/order-approved', [
            'id' => 'ORDER-APPROVE-2',
        ]))->assertOk();

        $this->assertSame('COMPLETED', $payment->fresh()->status);
    }

    public function test_capture_completed_webhook_finds_payment_via_related_order_id(): void
    {
        Mail::fake();

        $order = Order::factory()->create(['status' => 'pending', 'payment_method' => 'paypal']);
        $payment = Payment::factory()->created()->for($order)->create([
            'paypal_order_id' => 'ORDERRELATED1',
            'amount' => $order->total_amount,
        ]);

        $this->withVerifiedWebhook();

        // Die Capture-ID ist noch nicht gespeichert: Zuordnung nur über
        // supplementary_data.related_ids.order_id.
        $this->postJson(route('paypal.webhook'), $this->captureCompletedEvent('CAPTURE-RELATED-1', $order->total_amount, [
            'supplementary_data' => ['related_ids' => ['order_id' => 'ORDERRELATED1']],
        ]))->assertOk();

        $payment->refresh();
        $this->assertSame('COMPLETED', $payment->status);
        $this->assertSame('CAPTURE-RELATED-1', $payment->paypal_capture_id);
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_capture_completed_webhook_for_unknown_capture_changes_nothing(): void
    {
        Mail::fake();

        $order = Order::factory()->create(['status' => 'pending', 'payment_method' => 'paypal']);
        $payment = Payment::factory()->created()->for($order)->create(['paypal_order_id' => 'ORDER-KNOWN']);

        $this->withVerifiedWebhook();

        $this->postJson(route('paypal.webhook'), $this->captureCompletedEvent('CAPTURE-UNKNOWN', $order->total_amount, [
            'supplementary_data' => ['related_ids' => ['order_id' => 'ORDERUNKNOWN']],
        ]))->assertOk();

        $this->assertSame('CREATED', $payment->fresh()->status);
        $this->assertSame('pending', $order->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_redelivered_capture_completed_webhook_sends_confirmations_only_once(): void
    {
        Mail::fake();

        $order = Order::factory()->create(['status' => 'pending', 'payment_method' => 'paypal']);
        Payment::factory()->for($order)->create([
            'status' => 'APPROVED',
            'paypal_capture_id' => 'CAPTURE-TWICE',
            'amount' => $order->total_amount,
        ]);

        // PayPal liefert at-least-once: dasselbe Event kommt zweimal an.
        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldReceive('verifyWebhook')->twice()->andReturn(true);
        });

        $payload = $this->captureCompletedEvent('CAPTURE-TWICE', $order->total_amount);

        $this->postJson(route('paypal.webhook'), $payload)->assertOk();
        $this->postJson(route('paypal.webhook'), $payload)->assertOk();

        $this->assertSame('paid', $order->fresh()->status);
        Mail::assertSent(OrderConfirmationMail::class, 1);
        Mail::assertSent(NewOrderMail::class, 1);
    }

    public function test_unhandled_event_type_is_acknowledged_without_changes(): void
    {
        $order = Order::factory()->create(['status' => 'paid', 'payment_method' => 'paypal']);
        $payment = Payment::factory()->completed()->for($order)->create();

        $this->withVerifiedWebhook();

        // Bewusst kein vollständiges PayPal-Event: Nur der Typ zählt.
        $this->postJson(route('paypal.webhook'), [
            'event_type' => 'CUSTOMER.DISPUTE.CREATED',
            'resource' => ['id' => $payment->paypal_capture_id],
        ])->assertOk();

        $this->assertSame('COMPLETED', $payment->fresh()->status);
        $this->assertSame('paid', $order->fresh()->status);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function handledEventTypes(): array
    {
        return [
            'order approved' => ['CHECKOUT.ORDER.APPROVED'],
            'capture completed' => ['PAYMENT.CAPTURE.COMPLETED'],
            'capture refunded' => ['PAYMENT.CAPTURE.REFUNDED'],
            'capture denied' => ['PAYMENT.CAPTURE.DENIED'],
        ];
    }

    #[DataProvider('handledEventTypes')]
    public function test_event_without_resource_id_is_acknowledged_without_changes(string $eventType): void
    {
        $order = Order::factory()->create(['status' => 'pending', 'payment_method' => 'paypal']);
        $payment = Payment::factory()->created()->for($order)->create();

        $this->withVerifiedWebhook();

        // Bewusst kaputtes Event (Resource ohne id), keine PayPal-Fixture.
        $this->postJson(route('paypal.webhook'), [
            'event_type' => $eventType,
            'resource' => [],
        ])->assertOk();

        $this->assertSame('CREATED', $payment->fresh()->status);
        $this->assertSame('pending', $order->fresh()->status);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function settledOrderStatuses(): array
    {
        return [
            'paid' => ['paid'],
            'refunded' => ['refunded'],
        ];
    }

    #[DataProvider('settledOrderStatuses')]
    public function test_late_denied_webhook_does_not_fail_a_settled_order(string $orderStatus): void
    {
        $order = Order::factory()->create(['status' => $orderStatus, 'payment_method' => 'paypal']);
        $payment = Payment::factory()->completed()->for($order)->create();
        $paymentStatus = $payment->status;

        $this->withVerifiedWebhook();

        // PayPal liefert unsortiert: ein DENIED zu einer längst bezahlten
        // (oder erstatteten) Capture darf den Abschluss nicht zurückdrehen.
        $this->postJson(route('paypal.webhook'), PayPalFixtures::webhookEvent('PAYMENT.CAPTURE.DENIED', 'webhooks/capture-denied', [
            'id' => $payment->paypal_capture_id,
        ]))->assertOk();

        $this->assertSame($paymentStatus, $payment->fresh()->status);
        $this->assertSame($orderStatus, $order->fresh()->status);
    }

    public function test_partial_refund_webhook_does_not_mark_the_whole_order_refunded(): void
    {
        $order = Order::factory()->create(['status' => 'paid', 'payment_method' => 'paypal']);
        $payment = Payment::factory()->completed()->for($order)->create(['amount' => '100.00']);

        $this->withVerifiedWebhook();

        $this->postJson(route('paypal.webhook'), $this->refundEvent($payment->paypal_capture_id, '10.00'))
            ->assertOk();

        $this->assertSame('COMPLETED', $payment->fresh()->status);
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_full_refund_webhook_with_amount_marks_order_refunded(): void
    {
        $order = Order::factory()->create(['status' => 'paid', 'payment_method' => 'paypal']);
        $payment = Payment::factory()->completed()->for($order)->create(['amount' => '100.00']);

        $this->withVerifiedWebhook();

        $this->postJson(route('paypal.webhook'), $this->refundEvent($payment->paypal_capture_id, '100.00'))
            ->assertOk();

        $this->assertSame('REFUNDED', $payment->fresh()->status);
        $this->assertSame('refunded', $order->fresh()->status);
    }
}
