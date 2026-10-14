<?php

namespace Tests\Feature\Checkout;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Support\Cart\ShippingMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function completeAddress(): array
    {
        return [
            'first_name' => 'Erika',
            'last_name' => 'Mustermann',
            'street' => 'Musterstraße',
            'house_number' => '1',
            'zip' => '44135',
            'city' => 'Dortmund',
            'country' => 'DE',
        ];
    }

    public function test_confirm_requires_authentication(): void
    {
        $this->get(route('checkout.confirm'))->assertRedirect(route('login'));
    }

    public function test_address_update_requires_authentication(): void
    {
        $this->patch(route('checkout.address.update'))->assertRedirect(route('login'));
    }

    public function test_submit_requires_authentication(): void
    {
        $this->post(route('checkout.submit'))->assertRedirect(route('login'));
    }

    public function test_address_update_validates_required_shipping_fields(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer)
            ->patch(route('checkout.address.update'), [
                'shipping_address' => ['first_name' => 'Erika'],
            ])
            ->assertSessionHasErrors([
                'shipping_address.last_name',
                'shipping_address.street',
                'shipping_address.house_number',
                'shipping_address.zip',
                'shipping_address.city',
                'shipping_address.country',
            ]);
    }

    public function test_address_update_rejects_unsupported_country(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer)
            ->patch(route('checkout.address.update'), [
                'shipping_address' => [
                    ...$this->completeAddress(),
                    'country' => 'XX',
                ],
            ])
            ->assertSessionHasErrors(['shipping_address.country']);
    }

    public function test_address_update_stores_shipping_address_in_session(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer)
            ->patch(route('checkout.address.update'), [
                'shipping_address' => $this->completeAddress(),
                'billing_same_as_shipping' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('cart.shipping_address.city', 'Dortmund');
    }

    public function test_confirm_renders_when_cart_has_items(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['price' => '10.00']);

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
                    'shipping_method' => ShippingMethods::default()->key,
                    'shipping_address' => $this->completeAddress(),
                ],
            ])
            ->get(route('checkout.confirm'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Checkout/Confirm')->has('cart'));
    }

    public function test_submit_with_empty_cart_redirects_to_checkout_index(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer)
            ->post(route('checkout.submit'), ['agreed_to_terms' => true])
            ->assertRedirect(route('checkout.index'));
    }

    public function test_submit_requires_accepting_terms(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['price' => '10.00']);

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
                    'shipping_method' => ShippingMethods::default()->key,
                    'shipping_address' => $this->completeAddress(),
                ],
            ])
            ->post(route('checkout.submit'), ['agreed_to_terms' => false])
            ->assertSessionHasErrors('agreed_to_terms');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_confirm_exposes_paypal_client_id(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['price' => '10.00']);

        config(['paypal.sandbox.client_id' => 'sandbox-client-id']);

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
                    'shipping_method' => ShippingMethods::default()->key,
                    'shipping_address' => $this->completeAddress(),
                ],
            ])
            ->get(route('checkout.confirm'))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Checkout/Confirm')
                    ->where('paypal_client_id', 'sandbox-client-id')
            );
    }

    public function test_success_page_renders_for_own_order(): void
    {
        $customer = Customer::factory()->create();
        // Status fest setzen: Die Factory würfelt ihn, und cancelled/failed
        // leiten (gewollt) auf die Fehlerseite um.
        $order = Order::factory()->for($customer)->create([
            'status' => 'pending',
            'total_amount' => '20.00',
            'shipping_amount' => '0.00',
            'shipping_address' => $this->completeAddress(),
        ]);

        $this->actingAs($customer)
            ->get(route('checkout.success', ['order_id' => $order->id]))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Checkout/Success')
                    ->where('order_id', $order->id)
                    ->where('customer_email', $customer->email)
            );
    }

    public function test_success_page_redirects_home_without_a_matching_order(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer)
            ->get(route('checkout.success'))
            ->assertRedirect(route('home'));
    }

    public function test_success_page_does_not_expose_another_customers_order(): void
    {
        $owner = Customer::factory()->create();
        $order = Order::factory()->for($owner)->create();

        $attacker = Customer::factory()->create();

        $this->actingAs($attacker)
            ->get(route('checkout.success', ['order_id' => $order->id]))
            ->assertRedirect(route('home'));
    }

    public function test_success_page_rejects_failed_and_cancelled_orders(): void
    {
        $customer = Customer::factory()->create();

        foreach (['failed', 'cancelled'] as $status) {
            $order = Order::factory()->for($customer)->create(['status' => $status]);

            $this->actingAs($customer)
                ->get(route('checkout.success', ['order_id' => $order->id]))
                ->assertRedirect(route('checkout.error'));
        }
    }
}
