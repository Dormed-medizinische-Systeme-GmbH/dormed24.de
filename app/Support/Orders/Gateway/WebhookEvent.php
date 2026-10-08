<?php

namespace App\Support\Orders\Gateway;

/**
 * Ein verifiziertes Webhook-Event in neutraler Form. Welche Felder gesetzt
 * sind, hängt vom Typ ab; fehlt die nötige Referenz, ändert der Abgleich
 * nichts.
 */
final readonly class WebhookEvent
{
    /**
     * @param  string  $name  Name des Events beim Zahlungsdienst, fürs Log
     * @param  string|null  $amount  Betrag der Capture bzw. der Erstattung
     * @param  array<string, mixed>  $raw  Unveränderte Resource fürs Log und response_data
     */
    public function __construct(
        public WebhookEventType $type,
        public string $name,
        public ?string $orderReference = null,
        public ?string $captureId = null,
        public ?string $amount = null,
        public ?string $currency = null,
        public ?string $fee = null,
        public array $raw = [],
    ) {}
}
