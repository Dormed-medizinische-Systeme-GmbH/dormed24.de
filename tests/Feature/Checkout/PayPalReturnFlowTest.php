<?php

namespace Tests\Feature\Checkout;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PayPalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PayPalReturnFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_after_payment_requires_authentication(): void
    {
        $payment = Payment::factory()->completed()->create();

        // S-3: Capture-Rueckkehr nur fuer den eingeloggten Kaeufer, nicht per
        // bekanntem Token durch Dritte triggerbar.
        $this->get(route('paypal.after-payment', ['token' => $payment->paypal_order_id]))
            ->assertRedirect(route('login'));
    }

    public function test_after_payment_without_token_redirects_to_checkout(): void
    {
        $this->actingAs(Customer::factory()->create())
            ->get(route('paypal.after-payment'))
            ->assertRedirect(route('checkout.index'));
    }

    public function test_after_payment_with_unknown_token_redirects_to_checkout(): void
    {
        $this->actingAs(Customer::factory()->create())
            ->get(route('paypal.after-payment', ['token' => 'UNKNOWN123']))
            ->assertRedirect(route('checkout.index'));
    }

    public function test_after_payment_with_completed_payment_redirects_to_success_with_token(): void
    {
        $payment = Payment::factory()->completed()->create();

        $this->actingAs($payment->order->customer)
            ->get(route('paypal.after-payment', ['token' => $payment->paypal_order_id]))
            ->assertRedirect(route('checkout.success', ['paypal_order_id' => $payment->paypal_order_id]));
    }

    public function test_after_payment_ignores_another_customers_token(): void
    {
        $payment = Payment::factory()->created()->create();

        $this->mock(PayPalService::class, function ($mock): void {
            // S-2: fremde Tokens duerfen keinen Capture ausloesen.
            $mock->shouldNotReceive('captureOrder');
        });

        $this->actingAs(Customer::factory()->create())
            ->get(route('paypal.after-payment', ['token' => $payment->paypal_order_id]))
            ->assertRedirect(route('checkout.index'));

        $this->assertSame('CREATED', $payment->fresh()->status);
    }

    public function test_after_payment_captures_pending_payment_and_redirects_to_success(): void
    {
        Mail::fake();

        $order = Order::factory()->create(['status' => 'pending']);
        $payment = Payment::factory()->created()->for($order)->create();

        $this->mock(PayPalService::class, function ($mock) use ($payment): void {
            $mock->shouldReceive('captureOrder')
                ->once()
                ->with($payment->paypal_order_id)
                ->andReturn(['status' => 'COMPLETED']);
            $mock->shouldReceive('getCaptureIdFromOrder')
                ->once()
                ->andReturn('CAPTURE123456789');
        });

        $this->actingAs($order->customer)
            ->get(route('paypal.after-payment', ['token' => $payment->paypal_order_id]))
            ->assertRedirect(route('checkout.success', ['paypal_order_id' => $payment->paypal_order_id]));

        $this->assertSame('COMPLETED', $payment->fresh()->status);
        $this->assertSame('CAPTURE123456789', $payment->fresh()->paypal_capture_id);
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_after_payment_with_failed_capture_redirects_to_error(): void
    {
        $payment = Payment::factory()->created()->create();

        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldReceive('captureOrder')->once()->andReturn(['status' => 'DECLINED']);
        });

        $this->actingAs($payment->order->customer)
            ->get(route('paypal.after-payment', ['token' => $payment->paypal_order_id]))
            ->assertRedirect(route('checkout.error'));
    }

    public function test_success_page_renders_order_via_paypal_order_id(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create([
            'status' => 'paid',
            'total_amount' => '50.00',
            'shipping_amount' => '0.00',
        ]);
        $payment = Payment::factory()->completed()->for($order)->create();

        $this->actingAs($customer)
            ->get(route('checkout.success', ['paypal_order_id' => $payment->paypal_order_id]))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Checkout/Success')
                    ->where('order_id', $order->id)
            );
    }

    public function test_success_page_requires_authentication_for_paypal_order_id(): void
    {
        $order = Order::factory()->create(['status' => 'paid']);
        $payment = Payment::factory()->completed()->for($order)->create();

        $this->get(route('checkout.success', ['paypal_order_id' => $payment->paypal_order_id]))
            ->assertRedirect(route('login'));
    }

    public function test_success_page_does_not_expose_another_customers_order_via_paypal_order_id(): void
    {
        $owner = Customer::factory()->create();
        $order = Order::factory()->for($owner)->create(['status' => 'paid']);
        $payment = Payment::factory()->completed()->for($order)->create();

        $attacker = Customer::factory()->create();

        $this->actingAs($attacker)
            ->get(route('checkout.success', ['paypal_order_id' => $payment->paypal_order_id]))
            ->assertRedirect(route('home'));
    }
}
