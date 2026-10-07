<?php

namespace Tests\Feature\Admin;

use App\Mail\NewOrderMail;
use App\Mail\OrderConfirmationMail;
use App\Mail\PaymentConfirmationMail;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PayPalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_orders(): void
    {
        Order::factory()->count(2)->create();

        $this->actingAsAdmin()
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Orders/Index')->has('orders'));
    }

    public function test_show_renders_order(): void
    {
        $order = Order::factory()->create();

        $this->actingAsAdmin()
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Admin/Orders/Show')
                    ->where('order.id', $order->id)
            );
    }

    public function test_show_does_not_expose_raw_paypal_response_data(): void
    {
        $order = Order::factory()->create(['payment_method' => 'paypal']);
        Payment::factory()->completed()->for($order)->create([
            'response_data' => ['secret_payer_detail' => 'nicht-nach-vorne'],
        ]);

        $this->actingAsAdmin()
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->has('order.payments', 1)
                    ->has('order.payments.0.status')
                    ->missing('order.payments.0.response_data')
            );
    }

    public function test_confirm_payment_rejects_paypal_orders(): void
    {
        Mail::fake();
        $order = Order::factory()->create(['status' => 'pending', 'payment_method' => 'paypal']);

        $this->actingAsAdmin()
            ->patchJson(route('admin.orders.confirm-payment', $order))
            ->assertStatus(422);

        $this->assertSame('pending', $order->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_confirm_payment_rejects_already_paid_orders(): void
    {
        Mail::fake();
        $order = Order::factory()->create(['status' => 'paid', 'payment_method' => 'invoice']);

        $this->actingAsAdmin()
            ->patchJson(route('admin.orders.confirm-payment', $order))
            ->assertStatus(422);

        Mail::assertNothingSent();
    }

    public function test_marking_paid_with_notify_sends_payment_confirmation_to_customer_only(): void
    {
        Mail::fake();
        $order = Order::factory()->create(['status' => 'pending', 'payment_method' => 'invoice']);

        $this->actingAsAdmin()
            ->patchJson(route('admin.orders.confirm-payment', $order), ['notify' => true])
            ->assertOk();

        $this->assertSame('paid', $order->fresh()->status);
        // Dedicated payment-confirmation mail to the customer - not the
        // generic order-confirmation (which still asks to transfer money),
        // and never the operator notification (they're the one confirming).
        Mail::assertSent(PaymentConfirmationMail::class);
        Mail::assertNotSent(OrderConfirmationMail::class);
        Mail::assertNotSent(NewOrderMail::class);
    }

    public function test_marking_paid_without_notify_does_not_send_mail(): void
    {
        Mail::fake();
        $order = Order::factory()->create(['status' => 'pending', 'payment_method' => 'invoice']);

        $this->actingAsAdmin()
            ->patchJson(route('admin.orders.confirm-payment', $order), ['notify' => false])
            ->assertOk();

        $this->assertSame('paid', $order->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_refund_fails_without_a_paypal_payment(): void
    {
        $order = Order::factory()->create();

        $this->actingAsAdmin()
            ->postJson(route('admin.orders.refund', $order))
            ->assertStatus(422)
            ->assertJson(['message' => 'Keine erstattbare PayPal-Zahlung gefunden.']);
    }

    public function test_confirm_payment_requires_admin(): void
    {
        $order = Order::factory()->create(['status' => 'pending', 'payment_method' => 'invoice']);

        $this->patchJson(route('admin.orders.confirm-payment', $order))
            ->assertRedirect(route('admin.login'));
    }

    private function paidPaypalOrderWithCapture(): Payment
    {
        $order = Order::factory()->create(['status' => 'paid', 'payment_method' => 'paypal']);

        return Payment::factory()->completed()->for($order)->create(['amount' => '49.90']);
    }

    public function test_refund_marks_payment_and_order_refunded_when_paypal_confirms(): void
    {
        $payment = $this->paidPaypalOrderWithCapture();

        $this->mock(PayPalService::class, function ($mock) use ($payment): void {
            $mock->shouldReceive('refundOrder')
                ->once()
                ->with($payment->paypal_capture_id, 49.9)
                ->andReturn(['id' => 'REFUND1', 'status' => 'COMPLETED']);
        });

        $this->actingAsAdmin()
            ->postJson(route('admin.orders.refund', $payment->order))
            ->assertOk()
            ->assertJson(['status' => 'refunded']);

        $this->assertSame('REFUNDED', $payment->fresh()->status);
        $this->assertSame('refunded', $payment->order->fresh()->status);
    }

    /**
     * Zahlungen aus der Zeit vor dem Fix haben amount = 0. Dann wird der
     * Bestellbetrag erstattet: Eine bezahlte Order hat ihn nachweislich
     * eingezogen, sonst wäre sie am Betragsabgleich gescheitert.
     */
    public function test_refund_falls_back_to_order_total_when_payment_amount_is_missing(): void
    {
        $order = Order::factory()->create(['status' => 'paid', 'payment_method' => 'paypal', 'total_amount' => '4390.00']);
        $payment = Payment::factory()->completed()->for($order)->create(['amount' => 0]);

        $this->mock(PayPalService::class, function ($mock) use ($payment): void {
            $mock->shouldReceive('refundOrder')
                ->once()
                ->with($payment->paypal_capture_id, 4390.0)
                ->andReturn(['id' => 'REFUND-FALLBACK', 'status' => 'COMPLETED']);
        });

        $this->actingAsAdmin()
            ->postJson(route('admin.orders.refund', $order))
            ->assertOk()
            ->assertJson(['status' => 'refunded']);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function rejectedRefundResponses(): array
    {
        return [
            // Das SDK liefert API-Fehler als Array statt als Exception.
            'error array' => [['type' => 'error', 'message' => 'INSTRUMENT_DECLINED']],
            'error name' => [['name' => 'UNPROCESSABLE_ENTITY', 'details' => [['issue' => 'CAPTURE_FULLY_REFUNDED']]]],
            'refund failed' => [['id' => 'REFUND2', 'status' => 'FAILED']],
            'refund cancelled' => [['id' => 'REFUND3', 'status' => 'CANCELLED']],
            'empty response' => [[]],
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     */
    #[DataProvider('rejectedRefundResponses')]
    public function test_refund_is_not_booked_when_paypal_does_not_confirm_it(array $response): void
    {
        $payment = $this->paidPaypalOrderWithCapture();

        $this->mock(PayPalService::class, function ($mock) use ($response): void {
            $mock->shouldReceive('refundOrder')->once()->andReturn($response);
        });

        $this->actingAsAdmin()
            ->postJson(route('admin.orders.refund', $payment->order))
            ->assertStatus(422);

        $this->assertSame('COMPLETED', $payment->fresh()->status);
        $this->assertSame('paid', $payment->order->fresh()->status);
    }

    public function test_pending_refund_is_acknowledged_but_not_booked_as_refunded(): void
    {
        $payment = $this->paidPaypalOrderWithCapture();

        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldReceive('refundOrder')->once()->andReturn(['id' => 'REFUND4', 'status' => 'PENDING']);
        });

        $this->actingAsAdmin()
            ->postJson(route('admin.orders.refund', $payment->order))
            ->assertStatus(202)
            ->assertJson(['status' => 'pending']);

        // Den Abschluss meldet PayPal per CAPTURE.REFUNDED-Webhook.
        $this->assertSame('COMPLETED', $payment->fresh()->status);
        $this->assertSame('paid', $payment->order->fresh()->status);
    }

    public function test_refund_exception_leaves_payment_and_order_untouched(): void
    {
        $payment = $this->paidPaypalOrderWithCapture();

        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldReceive('refundOrder')->once()->andThrow(new \RuntimeException('timeout'));
        });

        $this->actingAsAdmin()
            ->postJson(route('admin.orders.refund', $payment->order))
            ->assertStatus(422);

        $this->assertSame('COMPLETED', $payment->fresh()->status);
        $this->assertSame('paid', $payment->order->fresh()->status);
    }

    public function test_refund_requires_admin(): void
    {
        $payment = $this->paidPaypalOrderWithCapture();

        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldNotReceive('refundOrder');
        });

        $this->postJson(route('admin.orders.refund', $payment->order))->assertRedirect(route('admin.login'));

        $this->assertSame('COMPLETED', $payment->fresh()->status);
    }
}
