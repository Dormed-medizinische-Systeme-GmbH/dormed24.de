<?php

namespace App\Support\Cart;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Liefer- oder Rechnungsadresse im Warenkorb.
 *
 * @implements Arrayable<string, string|null>
 */
final readonly class CartAddress implements Arrayable
{
    /**
     * @var array{company: string, salutation: string, first_name: string, last_name: string, street: string, house_number: string, address_line2: string, zip: string, city: string, country: string, phone: string}
     */
    public const DEFAULTS = [
        'company' => '',
        'salutation' => '',
        'first_name' => '',
        'last_name' => '',
        'street' => '',
        'house_number' => '',
        'address_line2' => '',
        'zip' => '',
        'city' => '',
        'country' => 'DE',
        'phone' => '',
    ];

    public function __construct(
        public ?string $company,
        public ?string $salutation,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $street,
        public ?string $houseNumber,
        public ?string $addressLine2,
        public ?string $zip,
        public ?string $city,
        public ?string $country,
        public ?string $phone,
    ) {}

    /**
     * Fehlende Felder werden mit self::DEFAULTS aufgefüllt.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $data = array_merge(self::DEFAULTS, $data);
        $field = fn (string $key): ?string => is_scalar($data[$key]) ? (string) $data[$key] : null;

        return new self(
            company: $field('company'),
            salutation: $field('salutation'),
            firstName: $field('first_name'),
            lastName: $field('last_name'),
            street: $field('street'),
            houseNumber: $field('house_number'),
            addressLine2: $field('address_line2'),
            zip: $field('zip'),
            city: $field('city'),
            country: $field('country'),
            phone: $field('phone'),
        );
    }

    /**
     * Alle Pflichtfelder für den Versand sind ausgefüllt.
     */
    public function isComplete(): bool
    {
        return ! empty($this->firstName)
            && ! empty($this->lastName)
            && ! empty($this->street)
            && ! empty($this->houseNumber)
            && ! empty($this->zip)
            && ! empty($this->city);
    }

    /**
     * @return array{company: string|null, salutation: string|null, first_name: string|null, last_name: string|null, street: string|null, house_number: string|null, address_line2: string|null, zip: string|null, city: string|null, country: string|null, phone: string|null}
     */
    public function toArray(): array
    {
        return [
            'company' => $this->company,
            'salutation' => $this->salutation,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'street' => $this->street,
            'house_number' => $this->houseNumber,
            'address_line2' => $this->addressLine2,
            'zip' => $this->zip,
            'city' => $this->city,
            'country' => $this->country,
            'phone' => $this->phone,
        ];
    }
}
