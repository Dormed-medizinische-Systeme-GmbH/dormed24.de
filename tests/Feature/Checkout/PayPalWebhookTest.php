<?php

namespace Tests\Feature\Checkout;

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
}
