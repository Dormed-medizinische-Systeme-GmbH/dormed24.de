<?php

namespace App\Support\Orders\Gateway;

/**
 * Antwort des Zahlungsdienstes auf das Anlegen einer Zahlung. Ohne
 * Referenz ist das Anlegen fehlgeschlagen.
 */
final readonly class CreatedPayment
{
    /**
     * @param  array<string, mixed>  $raw  Unveränderte Antwort fürs Log und response_data
     */
    public function __construct(
        public ?string $orderReference,
        public ?string $status,
        public array $raw = [],
    ) {}

    public function succeeded(): bool
    {
        return $this->orderReference !== null;
    }
}
