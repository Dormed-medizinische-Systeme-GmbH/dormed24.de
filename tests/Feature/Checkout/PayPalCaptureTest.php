<?php

namespace Tests\Feature\Checkout;

use App\Mail\PaymentAmountMismatchMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Services\PayPalService;
use App\Support\Orders\PaymentMethod;
use App\Support\Orders\OrderStatus;
use App\Support\Orders\PaymentReconciliation;
use App\Support\Orders\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\PayPalFixtures;
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

        $this->assertSame(PaymentStatus::Created, $payment->fresh()->status);
    }

    public function test_capture_succeeds_for_own_payment(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create(['status' => 'pending']);
        $payment = Payment::factory()->created()->for($order)->create();

        $this->partialMock(PayPalService::class, function ($mock) use ($payment, $order): void {
            $mock->shouldReceive('captureOrder')
                ->once()
                ->with($payment->paypal_order_id)
                ->andReturn(PayPalFixtures::capture('orders/capture-completed', [
                    'id' => 'CAPTURE123456789',
                    'amount' => ['value' => $order->total_amount],
                ]));
        });

        $this->actingAs($customer)
            ->postJson(route('paypal.order.capture'), [
                'paypal_order_id' => $payment->paypal_order_id,
            ])
            ->assertOk()
            ->assertJson(['status' => 'COMPLETED']);

        $this->assertSame(PaymentStatus::Completed, $payment->fresh()->status);
        $this->assertSame('CAPTURE123456789', $payment->fresh()->paypal_capture_id);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    /**
     * Beim Anlegen kennt PayPal nur id/status/links, der Betrag steht erst in
     * der Capture-Antwort. Ohne Nachtragen blieb amount auf 0 und die
     * Admin-Erstattung schickte 0,00 € an PayPal (422).
     */
    public function test_capture_stores_captured_amount_currency_and_fee(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create(['status' => 'pending', 'total_amount' => '4390.00']);
        $payment = Payment::factory()->created()->for($order)->create(['amount' => 0, 'fee' => null]);

        $this->partialMock(PayPalService::class, function ($mock): void {
            // Echte Capture-Antwort: 4.390,00 €, Gebühr 153,99 €.
            $mock->shouldReceive('captureOrder')->once()->andReturn(PayPalFixtures::sdkResponse('orders/capture-completed'));
        });

        $this->actingAs($customer)
            ->postJson(route('paypal.order.capture'), ['paypal_order_id' => $payment->paypal_order_id])
            ->assertOk();

        $payment->refresh();
        $this->assertSame(PaymentStatus::Completed, $payment->status);
        $this->assertEquals(4390.00, (float) $payment->amount);
        $this->assertSame('EUR', $payment->currency);
        $this->assertEquals(153.99, (float) $payment->fee);
    }

    public function test_recording_a_created_order_uses_the_order_total_as_amount(): void
    {
        $order = Order::factory()->create(['total_amount' => '4390.00']);

        $this->mock(PayPalService::class, fn ($mock) => $mock->shouldReceive('createOrder')->once()->andReturn(PayPalFixtures::sdkResponse('orders/create')));

        $payment = app(PaymentReconciliation::class)->start($order);

        $this->assertNotNull($payment);
        $this->assertSame(PaymentStatus::Created, $payment->status);
        $this->assertEquals(4390.00, (float) $payment->amount);
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
                ->andReturn(PayPalFixtures::capture('orders/capture-completed', ['amount' => ['value' => '1.00']]));
        });

        $this->actingAs($customer)
            ->postJson(route('paypal.order.capture'), [
                'paypal_order_id' => $payment->paypal_order_id,
            ])
            ->assertStatus(422)
            ->assertJson(['status' => 'FAILED']);

        // Geld ist eingezogen: Capture bleibt mit Betrag gespeichert und erstattbar.
        $this->assertSame(PaymentStatus::Mismatch, $payment->fresh()->status);
        $this->assertSame('5XV36579Y29910400', $payment->fresh()->paypal_capture_id);
        $this->assertEquals(1.00, (float) $payment->fresh()->amount);
        $this->assertSame(OrderStatus::Failed, $order->fresh()->status);
    }

    public function test_capture_in_foreign_currency_is_rejected_like_an_amount_mismatch(): void
    {
        Mail::fake();
        config(['shop.contact.email' => 'shop@dormed.example']);
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create(['status' => 'pending', 'total_amount' => '100.00']);
        $payment = Payment::factory()->created()->for($order)->create(['currency' => 'EUR']);

        $this->mock(PayPalService::class, fn ($mock) => $mock->shouldReceive('captureOrder')->once()->andReturn(
            PayPalFixtures::capture('orders/capture-completed', ['amount' => ['value' => '100.00', 'currency_code' => 'USD']]),
        ));

        $this->actingAs($customer)
            ->postJson(route('paypal.order.capture'), ['paypal_order_id' => $payment->paypal_order_id])
            ->assertUnprocessable();

        $this->assertSame(PaymentStatus::Mismatch, $payment->fresh()->status);
        $this->assertSame('USD', $payment->fresh()->currency);
        $this->assertSame(OrderStatus::Failed, $order->fresh()->status);
        Mail::assertSent(PaymentAmountMismatchMail::class);
    }

    public function test_capture_amount_mismatch_alerts_the_operator(): void
    {
        Mail::fake();
        config(['shop.contact.email' => 'shop@dormed.example']);

        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create(['status' => 'pending', 'total_amount' => '100.00']);
        $payment = Payment::factory()->created()->for($order)->create();

        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldReceive('captureOrder')->once()->andReturn(
                PayPalFixtures::capture('orders/capture-completed', ['amount' => ['value' => '1.00']]),
            );
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

        $this->partialMock(PayPalService::class, function ($mock) use ($order): void {
            // z. B. Prüfung bei PayPal: Die Order ist COMPLETED, die Capture
            // selbst aber noch PENDING. Maßgeblich ist der Capture-Status.
            $mock->shouldReceive('captureOrder')->once()->andReturn(
                PayPalFixtures::capture('orders/capture-pending', ['amount' => ['value' => $order->total_amount]]),
            );
        });

        $this->actingAs($customer)
            ->postJson(route('paypal.order.capture'), [
                'paypal_order_id' => $payment->paypal_order_id,
            ])
            ->assertStatus(422)
            ->assertJson(['status' => 'PENDING'])
            ->assertJsonPath('error', fn (string $message) => str_contains($message, 'geprüft'));

        // In Prüfung ist nicht fehlgeschlagen: die Bestellung bleibt offen,
        // der Webhook COMPLETED oder DENIED entscheidet.
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_new_paypal_attempt_does_not_cancel_an_order_whose_payment_is_in_review(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['price' => '10.00']);
        $inReview = Order::factory()->for($customer)->create(['status' => 'pending', 'payment_method' => 'paypal']);
        Payment::factory()->for($inReview)->create(['status' => 'PENDING']);
        $abandoned = Order::factory()->for($customer)->create(['status' => 'pending', 'payment_method' => 'paypal']);
        Payment::factory()->created()->for($abandoned)->create();

        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldReceive('createOrder')->once()->andReturn(PayPalFixtures::sdkResponse('orders/create'));
        });

        $this->actingAs($customer)
            ->withSession($this->cartSession($product))
            ->postJson(route('paypal.order.create'), ['agreed_to_terms' => true, 'funding_source' => 'paypal'])
            ->assertOk();

        $this->assertSame(OrderStatus::Pending, $inReview->fresh()->status);
        $this->assertSame(OrderStatus::Cancelled, $abandoned->fresh()->status);
    }

    /**
     * @return array<string, array{string, PaymentMethod}>
     */
    public static function fundingSources(): array
    {
        return [
            'PayPal' => ['paypal', PaymentMethod::Paypal],
            'Lastschrift' => ['sepa', PaymentMethod::Sepa],
            'Kreditkarte' => ['card', PaymentMethod::Card],
        ];
    }

    #[DataProvider('fundingSources')]
    public function test_create_order_stores_the_clicked_funding_source_as_payment_method(string $fundingSource, PaymentMethod $expected): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['price' => '10.00']);

        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldReceive('createOrder')->once()->andReturn(PayPalFixtures::sdkResponse('orders/create'));
        });

        $this->actingAs($customer)
            ->withSession($this->cartSession($product))
            ->postJson(route('paypal.order.create'), ['agreed_to_terms' => true, 'funding_source' => $fundingSource])
            ->assertOk();

        $this->assertSame($expected, Order::query()->sole()->payment_method);
    }

    public function test_create_order_rejects_missing_or_unknown_funding_source(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['price' => '10.00']);

        $this->actingAs($customer)->withSession($this->cartSession($product));

        $this->postJson(route('paypal.order.create'), ['agreed_to_terms' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('funding_source');

        $this->postJson(route('paypal.order.create'), ['agreed_to_terms' => true, 'funding_source' => 'invoice'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('funding_source');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_new_attempt_cancels_abandoned_card_and_sepa_orders(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['price' => '10.00']);
        $abandonedCard = Order::factory()->for($customer)->create(['status' => 'pending', 'payment_method' => PaymentMethod::Card]);
        Payment::factory()->created()->for($abandonedCard)->create();
        $abandonedSepa = Order::factory()->for($customer)->create(['status' => 'pending', 'payment_method' => PaymentMethod::Sepa]);
        Payment::factory()->created()->for($abandonedSepa)->create();
        $openInvoice = Order::factory()->for($customer)->create(['status' => 'pending', 'payment_method' => PaymentMethod::Invoice]);

        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldReceive('createOrder')->once()->andReturn(PayPalFixtures::sdkResponse('orders/create'));
        });

        $this->actingAs($customer)
            ->withSession($this->cartSession($product))
            ->postJson(route('paypal.order.create'), ['agreed_to_terms' => true, 'funding_source' => 'paypal'])
            ->assertOk();

        $this->assertSame(OrderStatus::Cancelled, $abandonedCard->fresh()->status);
        $this->assertSame(OrderStatus::Cancelled, $abandonedSepa->fresh()->status);
        $this->assertSame(OrderStatus::Pending, $openInvoice->fresh()->status);
    }

    public function test_declined_capture_in_completed_order_is_not_booked_as_paid(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create(['status' => 'pending']);
        $payment = Payment::factory()->created()->for($order)->create();

        $this->partialMock(PayPalService::class, function ($mock) use ($order): void {
            $mock->shouldReceive('captureOrder')->once()->andReturn(
                PayPalFixtures::capture('orders/capture-declined', ['amount' => ['value' => $order->total_amount]]),
            );
        });

        $this->actingAs($customer)
            ->postJson(route('paypal.order.capture'), ['paypal_order_id' => $payment->paypal_order_id])
            ->assertStatus(422);

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertNotSame(OrderStatus::Paid, $order->fresh()->status);
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
            ->postJson(route('paypal.order.create'), ['agreed_to_terms' => true, 'funding_source' => 'paypal'])
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
            $mock->shouldReceive('createOrder')->once()->andReturn(PayPalFixtures::sdkResponse('orders/create'));
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
            ->postJson(route('paypal.order.create'), ['agreed_to_terms' => true, 'funding_source' => 'paypal'])
            ->assertOk();

        $this->assertSame(OrderStatus::Cancelled, $staleOrder->fresh()->status);
        $this->assertSame(OrderStatus::Pending, $paidOrder->fresh()->status);
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
            ->postJson(route('paypal.order.create'), ['agreed_to_terms' => true, 'funding_source' => 'paypal']);

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

        $this->assertSame(PaymentStatus::Completed, $payment->fresh()->status);
        $this->assertSame('CAPTURE-WON-THE-RACE', $payment->fresh()->paypal_capture_id);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
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

        $this->assertSame(PaymentStatus::Completed, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
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

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Failed, $order->fresh()->status);
    }

    /**
     * @return array<string, mixed>
     */
    private function cartSession(Product $product, bool $completeAddress = true): array
    {
        return ['cart' => [
            'items' => [(string) $product->id => 1],
            'shipping_address' => [
                'first_name' => 'Erika',
                'last_name' => 'Mustermann',
                'street' => $completeAddress ? 'Musterstraße' : '',
                'house_number' => '1',
                'zip' => '44135',
                'city' => 'Dortmund',
                'country' => 'DE',
            ],
        ]];
    }

    public function test_create_order_rejects_empty_cart(): void
    {
        $this->mock(PayPalService::class, fn ($mock) => $mock->shouldNotReceive('createOrder'));

        $this->actingAs(Customer::factory()->create())
            ->postJson(route('paypal.order.create'), ['agreed_to_terms' => true, 'funding_source' => 'paypal'])
            ->assertStatus(400)
            ->assertJson(['error' => 'Warenkorb ist leer.']);

        $this->assertSame(0, Order::query()->count());
    }

    public function test_create_order_rejects_incomplete_shipping_address(): void
    {
        $product = Product::factory()->create(['price' => '10.00']);
        $this->mock(PayPalService::class, fn ($mock) => $mock->shouldNotReceive('createOrder'));

        $this->actingAs(Customer::factory()->create())
            ->withSession($this->cartSession($product, completeAddress: false))
            ->postJson(route('paypal.order.create'), ['agreed_to_terms' => true, 'funding_source' => 'paypal'])
            ->assertUnprocessable()
            ->assertJson(['error' => 'Bitte vervollständigen Sie Ihre Lieferadresse.']);

        $this->assertSame(0, Order::query()->count());
    }

    public function test_create_order_without_paypal_order_id_fails_the_order(): void
    {
        $product = Product::factory()->create(['price' => '10.00']);
        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldReceive('createOrder')->once()->andReturn(PayPalFixtures::sdkResponse('orders/create-unprocessable'));
        });

        $this->actingAs(Customer::factory()->create())
            ->withSession($this->cartSession($product))
            ->postJson(route('paypal.order.create'), ['agreed_to_terms' => true, 'funding_source' => 'paypal'])
            ->assertStatus(500)
            ->assertJson(['error' => 'PayPal-Order konnte nicht erstellt werden.']);

        $this->assertSame(OrderStatus::Failed, Order::query()->sole()->status);
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_capture_of_completed_payment_is_rejected_as_conflict(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create(['status' => 'paid']);
        $payment = Payment::factory()->completed()->for($order)->create();
        $this->mock(PayPalService::class, fn ($mock) => $mock->shouldNotReceive('captureOrder'));

        $this->actingAs($customer)
            ->postJson(route('paypal.order.capture'), ['paypal_order_id' => $payment->paypal_order_id])
            ->assertConflict();
    }

    public function test_amount_mismatch_alert_failure_is_logged_and_does_not_break_the_capture_response(): void
    {
        config(['shop.contact.email' => 'shop@dormed.example']);
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));
        Log::shouldReceive('channel')->with('mail')->andReturnSelf();
        Log::shouldReceive('error')->once()->with('Amount mismatch alert mail failed', \Mockery::type('array'));
        Log::shouldReceive('warning', 'info', 'error')->zeroOrMoreTimes();

        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create(['status' => 'pending', 'total_amount' => '100.00']);
        $payment = Payment::factory()->created()->for($order)->create();
        $this->mock(PayPalService::class, fn ($mock) => $mock->shouldReceive('captureOrder')->once()->andReturn(
            PayPalFixtures::capture('orders/capture-completed', ['amount' => ['value' => '1.00']]),
        ));

        $this->actingAs($customer)
            ->postJson(route('paypal.order.capture'), ['paypal_order_id' => $payment->paypal_order_id])
            ->assertUnprocessable()
            ->assertJson(['status' => 'FAILED']);
    }
}
