<?php

namespace Tests\Feature\Support;

use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Support\Cart\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hält die exakte Form des Warenkorbs fest, die Frontend, Checkout und
 * PayPal erwarten (Schlüssel, Reihenfolge, Typen, Formatierung).
 */
class CartShapeTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_array_has_the_expected_shape(): void
    {
        $standard = ShippingMethod::factory()->create(['name' => 'Standard', 'description' => 'DHL', 'price' => '4.90', 'sort_order' => 1]);
        $express = ShippingMethod::factory()->create(['name' => 'Express', 'description' => null, 'price' => '12.00', 'sort_order' => 2]);
        ShippingMethod::factory()->create(['name' => 'Spedition', 'price' => null, 'sort_order' => 3]);

        $manufacturer = Manufacturer::factory()->create(['name' => 'Mindray']);
        $couch = Product::factory()->create([
            'manufacturer_id' => $manufacturer->id,
            'name' => 'Massageliege',
            'description' => 'Stabil',
            'price' => '100.00',
        ]);
        $wide = ProductVariant::factory()->create(['product_id' => $couch->id, 'label' => 'Breit', 'price' => '149.50']);
        $image = ProductImage::query()->create(['product_id' => $couch->id, 'path' => 'products/liege.png', 'sort_order' => 0]);

        $gel = Product::factory()->create([
            'manufacturer_id' => null,
            'name' => 'Ultraschallgel',
            'description' => null,
            'price' => '9.99',
            'is_available' => false,
        ]);

        $shippingAddress = [
            'company' => 'Praxis Dr. Muster',
            'salutation' => 'Frau',
            'first_name' => 'Erika',
            'last_name' => 'Muster',
            'street' => 'Hauptstraße',
            'house_number' => '5',
            'address_line2' => '',
            'zip' => '12345',
            'city' => 'Berlin',
            'country' => 'DE',
            'phone' => '',
        ];

        session()->put([
            'cart' => [
                'items' => [
                    "{$couch->id}:{$wide->id}" => 2,
                    (string) $gel->id => 3,
                ],
                'shipping_method' => (string) $express->id,
                'shipping_address' => $shippingAddress,
                'billing_address' => ['city' => 'Hamburg'],
            ],
        ]);

        $cart = app(CartService::class)->cart()->toArray();

        $standardOption = [
            'id' => (string) $standard->id,
            'label' => 'Standard',
            'description' => 'DHL',
            'price' => '4.90',
            'price_cents' => 490,
            'selected' => false,
        ];
        $expressOption = [
            'id' => (string) $express->id,
            'label' => 'Express',
            'description' => null,
            'price' => '12.00',
            'price_cents' => 1200,
            'selected' => true,
        ];

        // 2 × 149,50 + 3 × 9,99 + 12,00 Versand = 340,97 brutto, 19 % MwSt.
        $this->assertSame([
            'items' => [
                [
                    'line_key' => "{$couch->id}:{$wide->id}",
                    'product_id' => $couch->id,
                    'variant_id' => $wide->id,
                    'variant_label' => 'Breit',
                    'name' => 'Massageliege – Breit',
                    'description' => 'Stabil',
                    'product_number' => (string) $couch->id,
                    'manufacturer_name' => 'Mindray',
                    'quantity' => 2,
                    'unit_price' => '149.50',
                    'line_total' => '299.00',
                    'line_total_cents' => 29900,
                    'image_url' => $image->url,
                    'product_url' => $couch->url(),
                    'is_available' => true,
                ],
                [
                    'line_key' => (string) $gel->id,
                    'product_id' => $gel->id,
                    'variant_id' => null,
                    'variant_label' => null,
                    'name' => 'Ultraschallgel',
                    'description' => null,
                    'product_number' => (string) $gel->id,
                    'manufacturer_name' => null,
                    'quantity' => 3,
                    'unit_price' => '9.99',
                    'line_total' => '29.97',
                    'line_total_cents' => 2997,
                    'image_url' => null,
                    'product_url' => $gel->url(),
                    'is_available' => false,
                ],
            ],
            'count' => 5,
            'is_empty' => false,
            'vat_rate' => 19,
            'shipping_methods' => [$standardOption, $expressOption],
            'selected_shipping_method' => $expressOption,
            'subtotal' => '328.97',
            'shipping_total' => '12.00',
            'total' => '340.97',
            'net_total' => '286.53',
            'vat_amount' => '54.44',
            'shipping_address' => $shippingAddress,
            'billing_address' => [...array_fill_keys(array_keys($shippingAddress), ''), 'country' => 'DE', 'city' => 'Hamburg'],
        ], $cart);
    }

    public function test_empty_cart_has_the_expected_shape(): void
    {
        $cart = app(CartService::class)->cart()->toArray();

        $this->assertSame([], $cart['items']);
        $this->assertSame(0, $cart['count']);
        $this->assertTrue($cart['is_empty']);
        $this->assertSame([], $cart['shipping_methods']);
        $this->assertNull($cart['selected_shipping_method']);
        $this->assertSame('0.00', $cart['subtotal']);
        $this->assertSame('0.00', $cart['shipping_total']);
        $this->assertSame('0.00', $cart['total']);
        $this->assertSame(CartService::DEFAULT_ADDRESS, $cart['shipping_address']);
        $this->assertNull($cart['billing_address']);
    }
}
