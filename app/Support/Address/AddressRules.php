<?php

namespace App\Support\Address;

class AddressRules
{
    /**
     * Lieferländer — muss mit dem Länder-Select im AddressForm
     * (resources/js/components/AddressForm.svelte) übereinstimmen. Ein freier
     * 2-Zeichen-String würde sonst bis in den PayPal-createOrder-Call wandern
     * und dort einen 500er statt einer Feldmeldung produzieren.
     */
    public const COUNTRIES = 'DE,AT,CH,NL,BE,LU,FR';

    /**
     * @var array<string, array<int, string>>
     */
    private const BASE = [
        'company' => ['nullable', 'string', 'max:255'],
        'salutation' => ['nullable', 'string', 'in:Herr,Frau,Firma'],
        'first_name' => ['required', 'string', 'max:255'],
        'last_name' => ['required', 'string', 'max:255'],
        'street' => ['required', 'string', 'max:255'],
        'house_number' => ['required', 'string', 'max:20'],
        'address_line2' => ['nullable', 'string', 'max:255'],
        'zip' => ['required', 'string', 'max:20'],
        'city' => ['required', 'string', 'max:255'],
        'country' => ['required', 'string', 'in:'.self::COUNTRIES],
        'phone' => ['nullable', 'string', 'max:50'],
    ];

    /**
     * Validation rules for an address, keyed by "{$prefix}.{$field}".
     *
     * @return array<string, array<int, string>>
     */
    public static function forPrefix(string $prefix, bool $required = true): array
    {
        return collect(self::BASE)
            ->mapWithKeys(fn (array $rules, string $field) => [
                "{$prefix}.{$field}" => $required ? $rules : array_map(
                    fn (string $rule): string => $rule === 'required' ? 'nullable' : $rule,
                    $rules
                ),
            ])
            ->all();
    }
}
