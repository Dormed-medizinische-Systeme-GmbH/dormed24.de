<?php

namespace Tests\Feature\Cart;

use App\Mail\NewOrderMail;
use App\Mail\OrderConfirmationMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CartFlowTest extends TestCase
{
    use RefreshDatabase;

    private ShippingMethod $freeShipping;

    /**
     * A complete shipping address that satisfies the checkout address guard.
     *
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

    protected function setUp(): void
    {
        parent::setUp();

        // Create a free (Selbstabholung) shipping method as default so totals are predictable
        $this->freeShipping = ShippingMethod::factory()->create([
            'name' => 'Selbstabholung',
            'price' => '0.00',
            'sort_order' => 1,
        ]);
    }

    public function test_product_can_be_added_to_the_cart(): void
    {
        $product = Product::factory()->create(['name' => 'Test Product', 'price' => '19.99']);

        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertRedirect();

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Checkout/Index')
                ->where('cart.count', 2)
                ->where('cart.items.0.product_id', $product->id)
                ->where('cart.total', '39.98'));
    }

    public function test_unavailable_product_cannot_be_added_to_the_cart(): void
    {
        $product = Product::factory()->create(['is_available' => false]);

        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertSessionHasErrors('product_id');
    }

    public function test_cart_reads_the_live_price_when_product_price_changes(): void
    {
        $product = Product::factory()->create(['name' => 'Live Price Product', 'price' => '19.99']);

        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertRedirect();

        $product->update(['price' => '99.99']);

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('cart.items.0.unit_price', '99.99')
                ->where('cart.total', '99.99'));
    }

    public function test_cart_item_quantity_can_be_updated(): void
    {
        $product = Product::factory()->create(['name' => 'Quantity Product', 'price' => '10.00']);

        $this->withSession([
            'cart' => [
                'items' => [
                    $product->id => [
                        'quantity' => 1,
                        'unit_price' => '10.00',
                        'name' => $product->name,
                        'product_number' => (string) $product->id,
                    ],
                ],
                'shipping_method' => (string) $this->freeShipping->id,
            ],
        ])->patch(route('cart.items.update', $product), [
            'quantity' => 3,
        ])
            ->assertRedirect()
            ->assertSessionHas("cart.items.{$product->id}", 3);
    }

    public function test_shipping_method_can_be_updated(): void
    {
        $expressShipping = ShippingMethod::factory()->create([
            'name' => 'Express',
            'price' => '15.00',
            'sort_order' => 2,
        ]);

        $this->withSession([
            'cart' => [
                'items' => [],
                'shipping_method' => (string) $this->freeShipping->id,
            ],
        ])->patch(route('cart.shipping.update'), [
            'shipping_method' => (string) $expressShipping->id,
        ])
            ->assertRedirect()
            ->assertSessionHas('cart.shipping_method', (string) $expressShipping->id);
    }

    public function test_checkout_confirm_redirects_when_cart_is_empty(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer)
            ->get(route('checkout.confirm'))
            ->assertRedirect(route('checkout.index'));
    }

    public function test_authenticated_customer_can_place_an_order(): void
    {
        Mail::fake();

        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['name' => 'Order Product', 'price' => '10.00']);

        $response = $this->actingAs($customer)
            ->withSession([
                'cart' => [
                    'items' => [
                        $product->id => [
                            'quantity' => 2,
                            'unit_price' => '10.00',
                            'name' => $product->name,
                            'product_number' => (string) $product->id,
                        ],
                    ],
                    'shipping_method' => (string) $this->freeShipping->id,
                    'shipping_address' => $this->completeAddress(),
                ],
            ])->post(route('checkout.submit'), [
                'agreed_to_terms' => true,
            ]);

        $order = Order::query()->first();

        $response->assertRedirect(route('checkout.success', ['order_id' => $order->id]));

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'status' => 'pending',
            'total_amount' => '20.00',
        ]);

        $this->assertSame(1, Order::query()->count());

        // Both the customer confirmation and the admin notification go out.
        Mail::assertSent(OrderConfirmationMail::class);
        Mail::assertSent(NewOrderMail::class);
    }

    public function test_order_submit_rejects_cart_with_meanwhile_unavailable_product(): void
    {
        Mail::fake();

        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['name' => 'Now Unavailable Product', 'price' => '10.00']);

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
                    'shipping_method' => (string) $this->freeShipping->id,
                    'shipping_address' => $this->completeAddress(),
                ],
            ]);

        $product->update(['is_available' => false]);

        $this->post(route('checkout.submit'), ['agreed_to_terms' => true])
            ->assertSessionHasErrors('cart');

        $this->assertSame(0, Order::query()->count());
        Mail::assertNothingSent();
    }

    public function test_order_submit_requires_a_complete_shipping_address(): void
    {
        Mail::fake();

        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['name' => 'Guarded Product', 'price' => '10.00']);

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
                    'shipping_method' => (string) $this->freeShipping->id,
                ],
            ])->post(route('checkout.submit'), [
                'agreed_to_terms' => true,
            ])
            ->assertSessionHasErrors('shipping_address');

        $this->assertSame(0, Order::query()->count());
        Mail::assertNothingSent();
    }

    public function test_variant_is_added_with_its_own_price_and_label(): void
    {
        $product = Product::factory()->create(['name' => 'Massageliege', 'price' => '100.00']);
        $variant = ProductVariant::factory()->for($product)->create(['label' => 'Breit', 'price' => '149.50']);

        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertRedirect();

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('cart.items.0.variant_id', $variant->id)
                ->where('cart.items.0.variant_label', 'Breit')
                ->where('cart.items.0.name', 'Massageliege – Breit')
                ->where('cart.items.0.unit_price', '149.50')
                ->where('cart.total', '299.00'));
    }

    public function test_product_with_variants_cannot_be_added_without_choosing_one(): void
    {
        $product = Product::factory()->create();
        ProductVariant::factory()->for($product)->create();

        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertSessionHasErrors('variant_id');
    }

    public function test_variant_of_another_product_is_rejected(): void
    {
        $product = Product::factory()->create();
        $foreignVariant = ProductVariant::factory()->for(Product::factory())->create();

        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'variant_id' => $foreignVariant->id,
            'quantity' => 1,
        ])->assertSessionHasErrors('variant_id');
    }

    public function test_different_variants_of_one_product_are_separate_cart_lines(): void
    {
        $product = Product::factory()->create(['price' => '10.00']);
        $small = ProductVariant::factory()->for($product)->create(['label' => 'S', 'price' => '10.00']);
        $large = ProductVariant::factory()->for($product)->create(['label' => 'L', 'price' => '20.00']);

        $this->post(route('cart.items.store'), [
            'product_id' => $product->id, 'variant_id' => $small->id, 'quantity' => 1,
        ])->assertRedirect();
        $this->post(route('cart.items.store'), [
            'product_id' => $product->id, 'variant_id' => $large->id, 'quantity' => 1,
        ])->assertRedirect();

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('cart.items', 2)
                ->where('cart.total', '30.00'));
    }

    public function test_variant_line_can_be_updated_and_removed(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $lineKey = "{$product->id}:{$variant->id}";

        $this->withSession([
            'cart' => [
                'items' => [$lineKey => 1],
                'shipping_method' => (string) $this->freeShipping->id,
            ],
        ])->patch(route('cart.items.update', [$product, $variant]), ['quantity' => 3])
            ->assertRedirect()
            ->assertSessionHas("cart.items.{$lineKey}", 3);

        $this->delete(route('cart.items.destroy', [$product, $variant]))
            ->assertRedirect();

        $this->assertArrayNotHasKey($lineKey, session('cart.items'));
    }

    public function test_variant_line_rejects_a_variant_of_another_product(): void
    {
        $product = Product::factory()->create();
        $foreignVariant = ProductVariant::factory()->for(Product::factory())->create();

        $this->patch(route('cart.items.update', [$product, $foreignVariant]), ['quantity' => 2])
            ->assertNotFound();
    }

    public function test_order_snapshot_captures_variant_price_and_label(): void
    {
        Mail::fake();

        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['name' => 'Massageliege', 'price' => '100.00']);
        $variant = ProductVariant::factory()->for($product)->create(['label' => 'Breit', 'price' => '149.50']);

        $this->actingAs($customer)
            ->withSession([
                'cart' => [
                    'items' => ["{$product->id}:{$variant->id}" => 2],
                    'shipping_method' => (string) $this->freeShipping->id,
                    'shipping_address' => $this->completeAddress(),
                ],
            ])->post(route('checkout.submit'), [
                'agreed_to_terms' => true,
            ])->assertRedirect();

        $order = Order::query()->firstOrFail();

        $this->assertSame('299.00', (string) $order->total_amount);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Massageliege – Breit',
            'unit_price' => '149.50',
            'quantity' => 2,
        ]);
    }

    public function test_deleted_variant_blocks_the_checkout(): void
    {
        Mail::fake();

        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['price' => '100.00']);
        $variant = ProductVariant::factory()->for($product)->create();
        $lineKey = "{$product->id}:{$variant->id}";

        $variant->delete();

        $this->actingAs($customer)
            ->withSession([
                'cart' => [
                    'items' => [$lineKey => 1],
                    'shipping_method' => (string) $this->freeShipping->id,
                    'shipping_address' => $this->completeAddress(),
                ],
            ])->post(route('checkout.submit'), [
                'agreed_to_terms' => true,
            ])->assertSessionHasErrors('cart');

        $this->assertSame(0, Order::query()->count());
        Mail::assertNothingSent();
    }

    public function test_cart_line_without_variant_is_blocked_once_the_product_gains_variants(): void
    {
        Mail::fake();

        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['name' => 'Druckerpapier', 'price' => '20.00']);

        // Alter Warenkorb: Produkt ohne Variante (Line-Key = productId), noch
        // bevor Varianten existierten.
        $this->actingAs($customer)
            ->withSession([
                'cart' => [
                    'items' => [(string) $product->id => 1],
                    'shipping_method' => (string) $this->freeShipping->id,
                    'shipping_address' => $this->completeAddress(),
                ],
            ]);

        // Danach bekommt das Produkt Varianten -> die variantenlose Zeile ist
        // preislich mehrdeutig und darf nicht mehr zum Basispreis durchgehen.
        ProductVariant::factory()->for($product)->create(['label' => 'Groß', 'price' => '35.00']);

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('cart.items.0.is_available', false));

        $this->post(route('checkout.submit'), ['agreed_to_terms' => true])
            ->assertSessionHasErrors('cart');

        $this->assertSame(0, Order::query()->count());
        Mail::assertNothingSent();
    }

    public function test_shipping_method_without_price_is_not_offered(): void
    {
        $onRequest = ShippingMethod::factory()->create([
            'name' => 'Spedition auf Anfrage',
            'price' => null,
            'sort_order' => 5,
        ]);

        // "Preis auf Anfrage" (NULL) darf im Self-Service-Checkout nicht als
        // 0,00 € auswählbar sein — die Methode taucht gar nicht erst auf.
        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where(
                'cart.shipping_methods',
                fn ($methods) => collect($methods)->pluck('id')->doesntContain((string) $onRequest->id)
            ));
    }
}
