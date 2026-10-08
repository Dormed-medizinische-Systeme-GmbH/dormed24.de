<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class Customer extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    protected $table = 'customers';

    /**
     * @return HasMany<Address, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest();
    }

    /**
     * @return HasMany<Rating, $this>
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    /**
     * Jüngste bezahlte Bestellung, die das Produkt enthält. Nur sie
     * berechtigt zu einer Bewertung: offene Rechnungen, fehlgeschlagene,
     * stornierte und erstattete Bestellungen zählen nicht.
     */
    public function paidOrderContaining(Product $product): ?Order
    {
        return $this->orders()
            ->paid()
            ->whereHas('items', fn ($query) => $query->where('product_id', $product->id))
            ->first();
    }

    public function hasRated(Product $product): bool
    {
        return $this->ratings()->where('product_id', $product->id)->exists();
    }

    /**
     * Gekauft und bezahlt, aber noch nicht bewertet.
     */
    public function canRate(Product $product): bool
    {
        return ! $this->hasRated($product) && $this->paidOrderContaining($product) !== null;
    }

    /**
     * The customer's default shipping address (type shipping or both).
     * Shared by checkout prefill and the address settings page.
     */
    public function defaultShippingAddress(): ?Address
    {
        return $this->addresses()
            ->whereIn('type', ['shipping', 'both'])
            ->where('is_default', true)
            ->first();
    }

    public function defaultBillingAddress(): ?Address
    {
        return $this->addresses()
            ->where('type', 'billing')
            ->where('is_default', true)
            ->first();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
