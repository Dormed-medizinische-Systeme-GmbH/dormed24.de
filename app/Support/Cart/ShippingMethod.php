<?php

namespace App\Support\Cart;

/**
 * Eine Versandart aus config/shipping.php (kein Eloquent-Model).
 */
final readonly class ShippingMethod
{
    public function __construct(
        public string $key,
        public string $name,
        public ?string $description,
        public int $priceCents,
    ) {}
}
