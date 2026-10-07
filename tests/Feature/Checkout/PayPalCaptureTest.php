<?php

namespace Tests\Feature\Checkout;

use App\Mail\PaymentAmountMismatchMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Setting;
use App\Services\PayPalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PayPalCaptureTest extends TestCase
{
    use RefreshDatabase;

    public function test_capture_requires_authentication(): void
    {
        $payment = Payment::factory()->created()->create();

        $this->post(route('paypal.order.capture'), [
            'paypal_order_id' => $payment->paypal_order_id,
        ])->assertRedirect(route('login'));
    }

    public function test_capture_rejects_another_customers_payment(): void
    {
        $owner = Customer::factory()->create();
        $order = Order::factory()->for($owner)->create(['status' => 'pending']);
        $payment = Payment::factory()->created()->for($order)->create();

        $attacker = Customer::factory()->create();

        $this->actingAs($attacker)
            ->postJson(route('paypal.order.capture'), [
                'paypal_order_id' => $payment->paypal_order_id,
            ])
            ->assertNotFound();

        $this->assertSame('CREATED', $payment->fresh()->status);
    }

    public function test_capture_succeeds_for_own_payment(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create(['status' => 'pending']);
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

        $this->actingAs($customer)
            ->postJson(route('paypal.order.capture'), [
                'paypal_order_id' => $payment->paypal_order_id,
            ])
            ->assertOk()
            ->assertJson(['status' => 'COMPLETED']);

        $this->assertSame('COMPLETED', $payment->fresh()->status);
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_capture_rejects_amount_mismatch(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create(['status' => 'pending', 'total_amount' => '100.00']);
        $payment = Payment::factory()->created()->for($order)->create();

        $this->mock(PayPalService::class, function ($mock) use ($payment): void {
            $mock->shouldReceive('captureOrder')
                ->once()
                ->with($payment->paypal_order_id)
                ->andReturn([
                    'status' => 'COMPLETED',
                    'purchase_units' => [[
                        'payments' => [
                            'captures' => [[
                                'amount' => ['value' => '1.00'],
                            ]],
                        ],
                    ]],
                ]);
        });

        $this->actingAs($customer)
            ->postJson(route('paypal.order.capture'), [
                'paypal_order_id' => $payment->paypal_order_id,
            ])
            ->assertStatus(422)
            ->assertJson(['status' => 'FAILED']);

        $this->assertSame('FAILED', $payment->fresh()->status);
        $this->assertSame('failed', $order->fresh()->status);
    }

    public function test_capture_amount_mismatch_alerts_the_operator(): void
    {
        Mail::fake();
        Setting::set('shop.email', 'shop@dormed.example');

        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create(['status' => 'pending', 'total_amount' => '100.00']);
        $payment = Payment::factory()->created()->for($order)->create();

        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldReceive('captureOrder')->once()->andReturn([
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'payments' => ['captures' => [['amount' => ['value' => '1.00']]]],
                ]],
            ]);
        });

        $this->actingAs($customer)
            ->postJson(route('paypal.order.capture'), [
                'paypal_order_id' => $payment->paypal_order_id,
            ])
            ->assertStatus(422);

        Mail::assertSent(PaymentAmountMismatchMail::class, fn ($mail): bool => $mail->hasTo('shop@dormed.example'));
    }

    public function test_capture_with_incomplete_status_returns_unprocessable(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create(['status' => 'pending']);
        $payment = Payment::factory()->created()->for($order)->create();

        $this->mock(PayPalService::class, function ($mock): void {
            // z. B. eCheck: Capture angenommen, aber noch nicht abgeschlossen.
            $mock->shouldReceive('captureOrder')->once()->andReturn(['status' => 'PENDING']);
        });

        $this->actingAs($customer)
            ->postJson(route('paypal.order.capture'), [
                'paypal_order_id' => $payment->paypal_order_id,
            ])
            ->assertStatus(422)
            ->assertJson(['status' => 'FAILED']);

        $this->assertSame('failed', $order->fresh()->status);
    }

    public function test_create_order_rejects_cart_with_unavailable_product(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['price' => '10.00', 'is_available' => false]);

        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldNotReceive('createOrder');
        });

        $this->actingAs($customer)
            ->withSession([
                'cart' => [
                    'items' => [
                        $product->id => [
                            'quantity' => 1,
                            'unit_price' => '10.00',
                            'name' => $product->name,
                            'product_number' => (string) $product->id,
                        ],
                    ],
                    'shipping_address' => [
                        'first_name' => 'Erika',
                        'last_name' => 'Mustermann',
                        'street' => 'Musterstraße',
                        'house_number' => '1',
                        'zip' => '44135',
                        'city' => 'Dortmund',
                        'country' => 'DE',
                    ],
                ],
            ])
            ->postJson(route('paypal.order.create'), ['agreed_to_terms' => true])
            ->assertStatus(422);

        $this->assertSame(0, Order::query()->count());
    }

    public function test_create_order_cancels_previous_abandoned_paypal_order(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['price' => '10.00']);

        $staleOrder = Order::factory()->for($customer)->create([
            'status' => 'pending',
            'payment_method' => 'paypal',
        ]);
        Payment::factory()->created()->for($staleOrder)->create();

        $paidOrder = Order::factory()->for($customer)->create([
            'status' => 'pending',
            'payment_method' => 'paypal',
        ]);
        Payment::factory()->completed()->for($paidOrder)->create();

        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldReceive('createOrder')->once()->andReturn(['id' => 'PAY123', 'status' => 'CREATED']);
            $mock->shouldReceive('recordPayment')->once()->andReturn(new Payment);
        });

        $this->actingAs($customer)
            ->withSession([
                'cart' => [
                    'items' => [
                        $product->id => [
                            'quantity' => 1,
                            'unit_price' => '10.00',
                            'name' => $product->name,
                            'product_number' => (string) $product->id,
                        ],
                    ],
                    'shipping_address' => [
                        'first_name' => 'Erika',
                        'last_name' => 'Mustermann',
                        'street' => 'Musterstraße',
                        'house_number' => '1',
                        'zip' => '44135',
                        'city' => 'Dortmund',
                        'country' => 'DE',
                    ],
                ],
            ])
            ->postJson(route('paypal.order.create'), ['agreed_to_terms' => true])
            ->assertOk();

        $this->assertSame('cancelled', $staleOrder->fresh()->status);
        $this->assertSame('pending', $paidOrder->fresh()->status);
    }

    public function test_create_order_error_response_does_not_leak_internals(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['price' => '10.00']);

        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldReceive('createOrder')
                ->once()
                ->andThrow(new \RuntimeException('secret internal detail'));
        });

        $response = $this->actingAs($customer)
            ->withSession([
                'cart' => [
                    'items' => [
                        $product->id => [
                            'quantity' => 1,
                            'unit_price' => '10.00',
                            'name' => $product->name,
                            'product_number' => (string) $product->id,
                        ],
                    ],
                    'shipping_address' => [
                        'first_name' => 'Erika',
                        'last_name' => 'Mustermann',
                        'street' => 'Musterstraße',
                        'house_number' => '1',
                        'zip' => '44135',
                        'city' => 'Dortmund',
                        'country' => 'DE',
                    ],
                ],
            ])
            ->postJson(route('paypal.order.create'), ['agreed_to_terms' => true]);

        $response->assertStatus(500);
        $this->assertArrayNotHasKey('debug', $response->json());
    }

    /**
     * Simuliert: Return-URL oder Webhook waren schneller und haben die
     * Zahlung zwischenzeitlich abgeschlossen, während unser Capture-Request
     * noch lief. Dessen Capture scheitert bei PayPal dann (bereits erfasst).
     */
    private function completeConcurrently(Payment $payment): void
    {
        $payment->update(['status' => 'COMPLETED', 'paypal_capture_id' => 'CAPTURE-WON-THE-RACE']);
        $payment->order->update(['status' => 'paid']);
    }

    public function test_failed_capture_does_not_downgrade_a_payment_completed_in_the_meantime(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create(['status' => 'pending']);
        $payment = Payment::factory()->created()->for($order)->create();

        $this->mock(PayPalService::class, function ($mock) use ($payment): void {
            $mock->shouldReceive('captureOrder')->once()->andReturnUsing(function () use ($payment): array {
                $this->completeConcurrently($payment);

                return ['name' => 'UNPROCESSABLE_ENTITY', 'details' => [['issue' => 'ORDER_ALREADY_CAPTURED']]];
            });
        });

        $this->actingAs($customer)
            ->postJson(route('paypal.order.capture'), ['paypal_order_id' => $payment->paypal_order_id])
            ->assertOk()
            ->assertJson(['status' => 'COMPLETED']);

        $this->assertSame('COMPLETED', $payment->fresh()->status);
        $this->assertSame('CAPTURE-WON-THE-RACE', $payment->fresh()->paypal_capture_id);
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_capture_exception_does_not_downgrade_a_payment_completed_in_the_meantime(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create(['status' => 'pending']);
        $payment = Payment::factory()->created()->for($order)->create();

        $this->mock(PayPalService::class, function ($mock) use ($payment): void {
            $mock->shouldReceive('captureOrder')->once()->andReturnUsing(function () use ($payment): never {
                $this->completeConcurrently($payment);

                throw new \RuntimeException('Timeout nach serverseitigem Capture');
            });
        });

        $this->actingAs($customer)
            ->postJson(route('paypal.order.capture'), ['paypal_order_id' => $payment->paypal_order_id])
            ->assertOk()
            ->assertJson(['status' => 'COMPLETED']);

        $this->assertSame('COMPLETED', $payment->fresh()->status);
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_capture_exception_still_fails_an_order_that_is_not_paid(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create(['status' => 'pending']);
        $payment = Payment::factory()->created()->for($order)->create();

        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldReceive('captureOrder')->once()->andThrow(new \RuntimeException('boom'));
        });

        $this->actingAs($customer)
            ->postJson(route('paypal.order.capture'), ['paypal_order_id' => $payment->paypal_order_id])
            ->assertStatus(500);

        $this->assertSame('FAILED', $payment->fresh()->status);
        $this->assertSame('failed', $order->fresh()->status);
    }
}
