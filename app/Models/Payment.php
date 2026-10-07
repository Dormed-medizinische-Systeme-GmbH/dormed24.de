<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'paypal_order_id',
    'paypal_payer_id',
    'paypal_capture_id',
    'status',
    'amount',
    'currency',
    'fee',
    'payer_email',
    'payer_name',
    'response_data',
])]
/**
 * @property int $id
 * @property int $order_id
 * @property string|null $paypal_order_id
 * @property string|null $paypal_payer_id
 * @property string|null $paypal_capture_id
 * @property string $status
 * @property string $amount
 * @property string $currency
 * @property string|null $fee
 * @property string|null $payer_email
 * @property string|null $payer_name
 * @property array<string, mixed>|null $response_data
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'response_data' => 'array',
        ];
    }

    /**
     * Eingezogener Betrag. Zahlungen von vor Oktober 2026 haben amount = 0,
     * weil er beim Capture nicht nachgetragen wurde. Dann gilt der
     * Bestellbetrag: Eine bezahlte Order hat ihn nachweislich eingezogen,
     * sonst wäre sie am Betragsabgleich im Capture-Pfad gescheitert.
     */
    public function capturedAmount(): float
    {
        return (float) $this->amount > 0 ? (float) $this->amount : (float) $this->order?->total_amount;
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
