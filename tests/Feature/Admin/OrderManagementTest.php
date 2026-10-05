<?php

namespace Tests\Feature\Admin;

use App\Mail\NewOrderMail;
use App\Mail\OrderConfirmationMail;
use App\Mail\PaymentConfirmationMail;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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
}
