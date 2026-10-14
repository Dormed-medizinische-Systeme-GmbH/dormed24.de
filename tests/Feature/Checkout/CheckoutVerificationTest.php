<?php

namespace Tests\Feature\Checkout;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Bestellungen dürfen erst nach bestätigter E-Mail-Adresse möglich sein —
 * der "verified"-Hebel auf Checkout- und PayPal-Bestellrouten.
 */
class CheckoutVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_customer_cannot_reach_checkout_confirm(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $this->actingAs($customer)
            ->get(route('checkout.confirm'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_unverified_customer_cannot_submit_an_order(): void
    {
        $customer = Customer::factory()->unverified()->create();
        $product = Product::factory()->create(['price' => '10.00']);

        $this->actingAs($customer)
            ->withSession([
                'cart' => [
                    'items' => [(string) $product->id => 1],
                ],
            ])
            ->post(route('checkout.submit'), ['agreed_to_terms' => true])
            ->assertRedirect(route('verification.notice'));

        $this->assertSame(0, Order::query()->count());
    }

    public function test_unverified_customer_cannot_create_a_paypal_order(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $this->actingAs($customer)
            ->postJson(route('paypal.order.create'), ['agreed_to_terms' => true, 'funding_source' => 'paypal'])
            ->assertForbidden();

        $this->assertSame(0, Order::query()->count());
    }

    public function test_registration_sends_the_verification_mail(): void
    {
        Notification::fake();

        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'neu@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => 'on',
        ]);

        $customer = Customer::query()->where('email', 'neu@example.com')->firstOrFail();

        $this->assertFalse($customer->hasVerifiedEmail());
        Notification::assertSentTo($customer, VerifyEmail::class);
    }

    public function test_verified_customer_reaches_checkout_confirm(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['price' => '10.00']);

        $this->actingAs($customer)
            ->withSession([
                'cart' => [
                    'items' => [(string) $product->id => 1],
                ],
            ])
            ->get(route('checkout.confirm'))
            ->assertOk();
    }
}
