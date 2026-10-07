<?php

namespace App\Models;

use Database\Factories\RatingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property int|null $customer_id
 * @property int|null $order_id
 * @property int $stars
 * @property string $content
 * @property Carbon|null $created_at
 */
#[Fillable(['product_id', 'customer_id', 'order_id', 'stars', 'content'])]
// Bewertungen werden anonym angezeigt: Kunden- und Bestellbezug nie ausliefern.
#[Hidden(['customer_id', 'order_id'])]
class Rating extends Model
{
    /** @use HasFactory<RatingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'stars' => 'integer',
        ];
    }

    /**
     * Aus einem bezahlten Kauf entstanden (im Gegensatz zum anonymen Bestand).
     */
    public function isVerifiedPurchase(): bool
    {
        return $this->order_id !== null;
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
