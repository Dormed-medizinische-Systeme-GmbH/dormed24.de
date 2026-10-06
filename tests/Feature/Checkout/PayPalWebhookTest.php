<?php

namespace Tests\Feature\Checkout;

use App\Mail\NewOrderMail;
use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PayPalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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

    public function test_webhook_rejects_unverified_requests(): void
    {
        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldReceive('verifyWebhook')->once()->andReturn(false);
        });

        $this->postJson(route('paypal.webhook'), [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        ])->assertStatus(400);
    }

    public function test_refund_webhook_updates_payment_and_order(): void
    {
        $order = Order::factory()->create(['status' => 'paid', 'payment_method' => 'paypal']);
        $payment = Payment::factory()->completed()->for($order)->create();

        $this->withVerifiedWebhook();

        // PAYMENT.CAPTURE.REFUNDED carries a refund resource: "id" is the
        // refund id, the capture id sits in the "up" link.
        $this->postJson(route('paypal.webhook'), [
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource' => [
                'id' => 'REFUND123',
                'links' => [
                    ['rel' => 'self', 'href' => 'https://api.paypal.com/v2/payments/refunds/REFUND123'],
                    ['rel' => 'up', 'href' => "https://api.paypal.com/v2/payments/captures/{$payment->paypal_capture_id}"],
                ],
            ],
        ])->assertOk();

        $this->assertSame('REFUNDED', $payment->fresh()->status);
        $this->assertSame('refunded', $order->fresh()->status);
    }

    public function test_denied_webhook_updates_payment_and_order(): void
    {
        $order = Order::factory()->create(['status' => 'pending', 'payment_method' => 'paypal']);
        $payment = Payment::factory()->completed()->for($order)->create();

        $this->withVerifiedWebhook();

        // PAYMENT.CAPTURE.DENIED carries the capture itself.
        $this->postJson(route('paypal.webhook'), [
            'event_type' => 'PAYMENT.CAPTURE.DENIED',
            'resource' => [
                'id' => $payment->paypal_capture_id,
            ],
        ])->assertOk();

        $this->assertSame('FAILED', $payment->fresh()->status);
        $this->assertSame('failed', $order->fresh()->status);
    }

    public function test_refund_webhook_for_unknown_capture_changes_nothing(): void
    {
        $order = Order::factory()->create(['status' => 'paid', 'payment_method' => 'paypal']);
        $payment = Payment::factory()->completed()->for($order)->create();

        $this->withVerifiedWebhook();

        $this->postJson(route('paypal.webhook'), [
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource' => [
                'id' => 'REFUND123',
                'links' => [
                    ['rel' => 'up', 'href' => 'https://api.paypal.com/v2/payments/captures/UNKNOWNCAPTURE'],
                ],
            ],
        ])->assertOk();

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
        $this->postJson(route('paypal.webhook'), [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => $payment->paypal_capture_id,
            ],
        ])->assertOk();

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

        $this->postJson(route('paypal.webhook'), [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'CAPTURE555',
                'amount' => ['value' => (string) $order->total_amount],
            ],
        ])->assertOk();

        $this->assertSame('COMPLETED', $payment->fresh()->status);
        $this->assertSame('paid', $order->fresh()->status);
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

        $this->postJson(route('paypal.webhook'), [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'CAPTURE777',
                'amount' => ['value' => '1.00'],
            ],
        ])->assertOk();

        $this->assertSame('APPROVED', $payment->fresh()->status);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_order_approved_webhook_marks_created_payment_as_approved(): void
    {
        $payment = Payment::factory()->created()->create(['paypal_order_id' => 'ORDER-APPROVE-1']);

        $this->withVerifiedWebhook();

        $this->postJson(route('paypal.webhook'), [
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => ['id' => 'ORDER-APPROVE-1'],
        ])->assertOk();

        $this->assertSame('APPROVED', $payment->fresh()->status);
    }

    public function test_order_approved_webhook_does_not_downgrade_a_completed_payment(): void
    {
        $payment = Payment::factory()->completed()->create(['paypal_order_id' => 'ORDER-APPROVE-2']);

        $this->withVerifiedWebhook();

        $this->postJson(route('paypal.webhook'), [
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => ['id' => 'ORDER-APPROVE-2'],
        ])->assertOk();

        $this->assertSame('COMPLETED', $payment->fresh()->status);
    }

    public function test_capture_completed_webhook_finds_payment_via_related_order_id(): void
    {
        Mail::fake();

        $order = Order::factory()->create(['status' => 'pending', 'payment_method' => 'paypal']);
        $payment = Payment::factory()->created()->for($order)->create([
            'paypal_order_id' => 'ORDER-RELATED-1',
            'amount' => $order->total_amount,
        ]);

        $this->withVerifiedWebhook();

        // Die Capture-ID ist noch nicht gespeichert: Zuordnung nur über
        // supplementary_data.related_ids.order_id.
        $this->postJson(route('paypal.webhook'), [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'CAPTURE-RELATED-1',
                'amount' => ['value' => (string) $order->total_amount],
                'supplementary_data' => ['related_ids' => ['order_id' => 'ORDER-RELATED-1']],
            ],
        ])->assertOk();

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

        $this->postJson(route('paypal.webhook'), [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'CAPTURE-UNKNOWN',
                'supplementary_data' => ['related_ids' => ['order_id' => 'ORDER-UNKNOWN']],
            ],
        ])->assertOk();

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

        $payload = [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'CAPTURE-TWICE',
                'amount' => ['value' => (string) $order->total_amount],
            ],
        ];

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

    #[\PHPUnit\Framework\Attributes\DataProvider('handledEventTypes')]
    public function test_event_without_resource_id_is_acknowledged_without_changes(string $eventType): void
    {
        $order = Order::factory()->create(['status' => 'pending', 'payment_method' => 'paypal']);
        $payment = Payment::factory()->created()->for($order)->create();

        $this->withVerifiedWebhook();

        $this->postJson(route('paypal.webhook'), [
            'event_type' => $eventType,
            'resource' => [],
        ])->assertOk();

        $this->assertSame('CREATED', $payment->fresh()->status);
        $this->assertSame('pending', $order->fresh()->status);
    }
}
