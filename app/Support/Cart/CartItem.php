<?php

namespace App\Support\Cart;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Eine Warenkorbzeile: ein Produkt, optional in einer Variante.
 *
 * @phpstan-type CartItemArray array{line_key: string, product_id: int, variant_id: int|null, variant_label: string|null, name: string, description: string|null, product_number: string, manufacturer_name: string|null, quantity: int, unit_price: string, line_total: string, line_total_cents: int, image_url: string|null, product_url: string, is_available: bool}
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class CartItem implements Arrayable
{
    public function __construct(
        public string $lineKey,
        public int $productId,
        public ?int $variantId,
        public ?string $variantLabel,
        public string $name,
        public ?string $description,
        public string $productNumber,
        public ?string $manufacturerName,
        public int $quantity,
        public int $unitPriceCents,
        public ?string $imageUrl,
        public string $productUrl,
        public bool $isAvailable,
    ) {}

    public function lineTotalCents(): int
    {
        return $this->unitPriceCents * $this->quantity;
    }

    /**
     * @return CartItemArray
     */
    public function toArray(): array
    {
        return [
            'line_key' => $this->lineKey,
            'product_id' => $this->productId,
            'variant_id' => $this->variantId,
            'variant_label' => $this->variantLabel,
            'name' => $this->name,
            'description' => $this->description,
            'product_number' => $this->productNumber,
            'manufacturer_name' => $this->manufacturerName,
            'quantity' => $this->quantity,
            'unit_price' => Money::format($this->unitPriceCents),
            'line_total' => Money::format($this->lineTotalCents()),
            'line_total_cents' => $this->lineTotalCents(),
            'image_url' => $this->imageUrl,
            'product_url' => $this->productUrl,
            'is_available' => $this->isAvailable,
        ];
    }
}
