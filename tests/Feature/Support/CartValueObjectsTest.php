<?php

namespace Tests\Feature\Support;

use App\Support\Cart\Cart;
use App\Support\Cart\CartAddress;
use App\Support\Cart\CartItem;
use App\Support\Cart\Money;
use App\Support\Cart\ShippingOption;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CartValueObjectsTest extends TestCase
{
    /**
     * @return array<string, array{0: string|int|float|null, 1: int}>
     */
    public static function amounts(): array
    {
        return [
            'Punkt' => ['149.50', 14950],
            'Komma' => ['149,5', 14950],
            'ganze Zahl' => [12, 1200],
            'Float' => [9.99, 999],
            'mehr als zwei Nachkommastellen' => ['1.999', 199],
            'null' => [null, 0],
        ];
    }

    #[DataProvider('amounts')]
    public function test_money_converts_amounts_to_cents(string|int|float|null $amount, int $expectedCents): void
    {
        $this->assertSame($expectedCents, Money::toCents($amount));
    }

    public function test_money_formats_cents_without_thousands_separator(): void
    {
        $this->assertSame('1234.05', Money::format(123405));
        $this->assertSame('0.00', Money::format(0));
    }

    public function test_address_fills_missing_fields_with_defaults(): void
    {
        $address = CartAddress::fromArray(['city' => 'Hamburg', 'company' => null]);

        $this->assertSame('Hamburg', $address->city);
        $this->assertSame('DE', $address->country);
        $this->assertNull($address->company);
        $this->assertSame(array_keys(CartAddress::DEFAULTS), array_keys($address->toArray()));
    }

    public function test_address_is_complete_only_with_all_required_fields(): void
    {
        $complete = [
            'first_name' => 'Erika',
            'last_name' => 'Muster',
            'street' => 'Hauptstraße',
            'house_number' => '5',
            'zip' => '12345',
            'city' => 'Berlin',
        ];

        $this->assertTrue(CartAddress::fromArray($complete)->isComplete());

        foreach (array_keys($complete) as $field) {
            $this->assertFalse(
                CartAddress::fromArray([...$complete, $field => ''])->isComplete(),
                "Ohne {$field} darf die Adresse nicht vollständig sein."
            );
        }
    }

    public function test_cart_without_selected_shipping_method_has_no_shipping_costs(): void
    {
        $cart = new Cart(
            items: [$this->item(quantity: 3, unitPriceCents: 1000, isAvailable: false)],
            vatRate: 19,
            shippingMethods: [new ShippingOption('standard', 'Standard', null, 490, false)],
            shippingAddress: CartAddress::fromArray([]),
            billingAddress: null,
        );

        $this->assertNull($cart->selectedShippingMethod());
        $this->assertSame(0, $cart->shippingCents());
        $this->assertSame(3000, $cart->totalCents());
        $this->assertSame(3, $cart->count());
        $this->assertFalse($cart->isEmpty());
        $this->assertTrue($cart->hasUnavailableItems());
        $this->assertNull($cart->toArray()['selected_shipping_method']);
    }

    private function item(int $quantity, int $unitPriceCents, bool $isAvailable): CartItem
    {
        return new CartItem(
            lineKey: '1',
            productId: 1,
            variantId: null,
            variantLabel: null,
            name: 'Testprodukt',
            description: null,
            productNumber: '1',
            manufacturerName: null,
            quantity: $quantity,
            unitPriceCents: $unitPriceCents,
            imageUrl: null,
            productUrl: 'https://example.com/produkte/1',
            isAvailable: $isAvailable,
        );
    }
}
