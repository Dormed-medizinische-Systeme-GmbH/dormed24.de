<?php

namespace App\Support\Cart;

use UnexpectedValueException;

/**
 * Zugriff auf die festen Versandarten aus config/shipping.php.
 *
 * Die Config wird bei jedem Zugriff vollständig geprüft. Ein fehlerhafter
 * Eintrag wirft eine Exception, statt still einen falschen Preis zu
 * berechnen; die CI prüft dieselben Regeln über errors(), damit es gar
 * nicht erst so weit kommt.
 *
 * @phpstan-type ShippingMethodEntry array{key: string, name: string, description?: string|null, price_cents: int}
 */
final class ShippingMethods
{
    public const KEY_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public const MAX_KEY_LENGTH = 64;

    public const MAX_NAME_LENGTH = 255;

    public const MAX_DESCRIPTION_LENGTH = 500;

    private const ALLOWED_FIELDS = ['key', 'name', 'description', 'price_cents'];

    /**
     * Alle Versandarten in der konfigurierten Reihenfolge.
     *
     * @return non-empty-list<ShippingMethod>
     *
     * @throws UnexpectedValueException wenn config/shipping.php ungültig ist
     */
    public static function all(): array
    {
        $entries = config('shipping');
        $errors = self::errors($entries);

        if ($errors !== []) {
            throw new UnexpectedValueException('config/shipping.php ist ungültig: '.implode(' ', $errors));
        }

        /** @var non-empty-list<ShippingMethodEntry> $entries */
        return array_map(
            fn (array $entry): ShippingMethod => new ShippingMethod(
                key: $entry['key'],
                name: $entry['name'],
                description: $entry['description'] ?? null,
                priceCents: $entry['price_cents'],
            ),
            $entries,
        );
    }

    public static function find(?string $key): ?ShippingMethod
    {
        if ($key === null || $key === '') {
            return null;
        }

        foreach (self::all() as $method) {
            if ($method->key === $key) {
                return $method;
            }
        }

        return null;
    }

    /**
     * Die erste Versandart der Config.
     */
    public static function default(): ShippingMethod
    {
        return self::all()[0];
    }

    /**
     * @return non-empty-list<string>
     */
    public static function keys(): array
    {
        return array_map(fn (ShippingMethod $method): string => $method->key, self::all());
    }

    /**
     * Prüft eine Versandarten-Liste und liefert alle Verstöße als Klartext.
     * Leere Liste = gültig.
     *
     * @return list<string>
     */
    public static function errors(mixed $entries): array
    {
        if (! is_array($entries) || ! array_is_list($entries)) {
            return ['Die Versandarten müssen eine Liste sein.'];
        }

        if ($entries === []) {
            return ['Es muss mindestens eine Versandart geben.'];
        }

        $errors = [];
        $seenKeys = [];

        foreach ($entries as $index => $entry) {
            $position = 'Eintrag '.($index + 1);

            if (! is_array($entry)) {
                $errors[] = "{$position}: muss ein Array sein.";

                continue;
            }

            $unknownFields = array_diff(array_keys($entry), self::ALLOWED_FIELDS);

            if ($unknownFields !== []) {
                $errors[] = "{$position}: unbekannte Felder ".implode(', ', $unknownFields).'.';
            }

            $key = $entry['key'] ?? null;

            if (! is_string($key) || strlen($key) > self::MAX_KEY_LENGTH || preg_match(self::KEY_PATTERN, $key) !== 1) {
                $errors[] = "{$position}: key muss aus Kleinbuchstaben, Ziffern und Bindestrichen bestehen (max. ".self::MAX_KEY_LENGTH.' Zeichen).';
            } elseif (in_array($key, $seenKeys, true)) {
                $errors[] = "{$position}: key \"{$key}\" ist doppelt.";
            } else {
                $seenKeys[] = $key;
            }

            $name = $entry['name'] ?? null;

            if (! is_string($name) || trim($name) === '' || mb_strlen($name) > self::MAX_NAME_LENGTH) {
                $errors[] = "{$position}: name muss ein nicht leerer Text sein (max. ".self::MAX_NAME_LENGTH.' Zeichen).';
            }

            $description = $entry['description'] ?? null;

            if ($description !== null && (! is_string($description) || mb_strlen($description) > self::MAX_DESCRIPTION_LENGTH)) {
                $errors[] = "{$position}: description muss null oder ein Text sein (max. ".self::MAX_DESCRIPTION_LENGTH.' Zeichen).';
            }

            $priceCents = $entry['price_cents'] ?? null;

            if (! is_int($priceCents) || $priceCents < 0) {
                $errors[] = "{$position}: price_cents muss eine ganze Zahl >= 0 sein (Cent, z. B. 900 für 9,00 €).";
            }
        }

        return $errors;
    }
}
