<?php

namespace App\Support\Orders\Gateway;

use App\Support\Orders\PaymentStatus;

final readonly class RefundResult
{
    /**
     * @param  PaymentStatus  $status  Refunded, Pending oder Failed
     * @param  array<string, mixed>  $raw  Unveränderte Antwort fürs Log
     */
    public function __construct(
        public PaymentStatus $status,
        public array $raw = [],
    ) {}
}
