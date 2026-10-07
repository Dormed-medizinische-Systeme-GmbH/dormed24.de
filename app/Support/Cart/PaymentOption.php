<?php

namespace App\Support\Cart;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Eine im Checkout wählbare Zahlart (aus config/shop.php).
 *
 * @phpstan-type PaymentOptionArray array{id: string, provider: string, label: string, description: string|null, selected: bool}
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class PaymentOption implements Arrayable
{
    public function __construct(
        public string $id,
        public string $provider,
        public string $label,
        public ?string $description,
        public bool $selected,
    ) {}

    /**
     * Bezeichnungen der konfigurierten Zahlarten, nach ID.
     *
     * @return array<string, string>
     */
    public static function configuredLabels(): array
    {
        /** @var array<string, array{methods?: list<array{id: string, label: string}>}> $providers */
        $providers = config('shop.cart.providers', []);
        $labels = [];

        foreach ($providers as $provider) {
            foreach ($provider['methods'] ?? [] as $method) {
                $labels[(string) $method['id']] = $method['label'];
            }
        }

        return $labels;
    }

    /**
     * @return PaymentOptionArray
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'label' => $this->label,
            'description' => $this->description,
            'selected' => $this->selected,
        ];
    }
}
