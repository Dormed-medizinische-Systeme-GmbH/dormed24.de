<?php

namespace App\Support\Cart;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Eine im Checkout wählbare Versandart.
 *
 * @phpstan-type ShippingOptionArray array{id: string, label: string, description: string|null, price: string, price_cents: int, selected: bool}
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class ShippingOption implements Arrayable
{
    public function __construct(
        public string $id,
        public string $label,
        public ?string $description,
        public int $priceCents,
        public bool $selected,
    ) {}

    /**
     * @return ShippingOptionArray
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'description' => $this->description,
            'price' => Money::format($this->priceCents),
            'price_cents' => $this->priceCents,
            'selected' => $this->selected,
        ];
    }
}
