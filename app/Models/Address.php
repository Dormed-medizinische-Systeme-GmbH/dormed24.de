<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $customer_id
 * @property string $type (shipping|billing|both)
 * @property string|null $company
 * @property string|null $salutation
 * @property string $first_name
 * @property string $last_name
 * @property string $street
 * @property string $house_number
 * @property string|null $address_line2
 * @property string $zip
 * @property string $city
 * @property string $country
 * @property string|null $phone
 * @property bool $is_default
 */
class Address extends Model
{
    protected $fillable = [
        'type', 'company', 'salutation',
        'first_name', 'last_name', 'street', 'house_number',
        'address_line2', 'zip', 'city', 'country', 'phone',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function streetWithNumber(): string
    {
        return trim($this->street.' '.$this->house_number);
    }

    /**
     * Normiertes Address-Array für Order-Snapshots / PayPal.
     *
     * @return array<string, string|null>
     */
    public function toAddressArray(): array
    {
        return [
            'company' => $this->company,
            'salutation' => $this->salutation,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'street' => $this->street,
            'house_number' => $this->house_number,
            'address_line2' => $this->address_line2,
            'zip' => $this->zip,
            'city' => $this->city,
            'country' => $this->country,
            'phone' => $this->phone,
        ];
    }
}
