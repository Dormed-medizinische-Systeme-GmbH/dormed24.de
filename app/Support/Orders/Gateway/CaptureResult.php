<?php

namespace App\Support\Orders\Gateway;

use App\Support\Orders\PaymentStatus;

/**
 * Ergebnis eines Einzugs. Maßgeblich ist der Status der Capture selbst,
 * nicht der der Order beim Zahlungsdienst.
 */
final readonly class CaptureResult
{
    /**
     * @param  PaymentStatus  $status  Completed, Pending oder Failed
     * @param  array<string, mixed>  $raw  Unveränderte Antwort fürs Log und response_data
     */
    public function __construct(
        public PaymentStatus $status,
        public ?string $captureId = null,
        public ?string $amount = null,
        public ?string $currency = null,
        public ?string $fee = null,
        public ?string $payerId = null,
        public ?string $payerEmail = null,
        public ?string $payerName = null,
        public array $raw = [],
    ) {}
}
