<?php

namespace App\Support\Cart;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Momentaufnahme des Warenkorbs mit Positionen, Versandarten,
 * Adressen und allen Summen. Beträge werden intern in Cents geführt,
 * toArray() liefert die Form, die Frontend und Inertia erwarten.
 *
 * @phpstan-import-type CartItemArray from CartItem
 * @phpstan-import-type ShippingOptionArray from ShippingOption
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class Cart implements Arrayable
{
    /**
     * @param  list<CartItem>  $items
     * @param  list<ShippingOption>  $shippingMethods
     */
    public function __construct(
        public array $items,
        public int $vatRate,
        public array $shippingMethods,
        public CartAddress $shippingAddress,
        public ?CartAddress $billingAddress,
    ) {}

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * Anzahl aller Artikel über alle Zeilen.
     */
    public function count(): int
    {
        $count = 0;

        foreach ($this->items as $item) {
            $count += $item->quantity;
        }

        return $count;
    }

    public function hasUnavailableItems(): bool
    {
        foreach ($this->items as $item) {
            if (! $item->isAvailable) {
                return true;
            }
        }

        return false;
    }

    public function selectedShippingMethod(): ?ShippingOption
    {
        foreach ($this->shippingMethods as $method) {
            if ($method->selected) {
                return $method;
            }
        }

        return null;
    }

    public function subtotalCents(): int
    {
        $subtotalCents = 0;

        foreach ($this->items as $item) {
            $subtotalCents += $item->lineTotalCents();
        }

        return $subtotalCents;
    }

    public function shippingCents(): int
    {
        return $this->selectedShippingMethod()->priceCents ?? 0;
    }

    public function totalCents(): int
    {
        return $this->subtotalCents() + $this->shippingCents();
    }

    public function netTotalCents(): int
    {
        return Money::netCents($this->totalCents(), $this->vatRate);
    }

    public function vatAmountCents(): int
    {
        return $this->totalCents() - $this->netTotalCents();
    }

    /**
     * @return array{items: list<CartItemArray>, count: int, is_empty: bool, vat_rate: int, shipping_methods: list<ShippingOptionArray>, selected_shipping_method: ShippingOptionArray|null, subtotal: string, shipping_total: string, total: string, net_total: string, vat_amount: string, shipping_address: array<string, string|null>, billing_address: array<string, string|null>|null}
     */
    public function toArray(): array
    {
        return [
            'items' => array_map(fn (CartItem $item): array => $item->toArray(), $this->items),
            'count' => $this->count(),
            'is_empty' => $this->isEmpty(),
            'vat_rate' => $this->vatRate,
            'shipping_methods' => array_map(fn (ShippingOption $method): array => $method->toArray(), $this->shippingMethods),
            'selected_shipping_method' => $this->selectedShippingMethod()?->toArray(),
            'subtotal' => Money::format($this->subtotalCents()),
            'shipping_total' => Money::format($this->shippingCents()),
            'total' => Money::format($this->totalCents()),
            'net_total' => Money::format($this->netTotalCents()),
            'vat_amount' => Money::format($this->vatAmountCents()),
            'shipping_address' => $this->shippingAddress->toArray(),
            'billing_address' => $this->billingAddress?->toArray(),
        ];
    }
}
