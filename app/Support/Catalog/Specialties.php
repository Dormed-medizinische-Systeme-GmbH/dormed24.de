<?php

namespace App\Support\Catalog;

/**
 * Zugriff auf die festen Fachrichtungen aus config/specialties.php.
 */
final class Specialties
{
    /**
     * Alle Fachrichtungen in der konfigurierten Reihenfolge.
     *
     * @return list<Specialty>
     */
    public static function all(): array
    {
        /** @var list<array{slug: string, name: string}> $entries */
        $entries = (array) config('specialties', []);

        return array_map(
            fn (array $entry): Specialty => new Specialty(slug: $entry['slug'], name: $entry['name']),
            $entries,
        );
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_map(fn (Specialty $specialty): string => $specialty->slug, self::all());
    }

    /**
     * Für Auswahllisten.
     *
     * @return list<array{slug: string, name: string}>
     */
    public static function options(): array
    {
        return array_map(fn (Specialty $specialty): array => $specialty->toArray(), self::all());
    }

    /**
     * Bekannte Slugs ohne Duplikate in Config-Reihenfolge; Unbekanntes fällt weg.
     *
     * @param  iterable<mixed>  $slugs
     * @return list<string>
     */
    public static function normalize(iterable $slugs): array
    {
        $given = [];

        foreach ($slugs as $slug) {
            if (is_string($slug)) {
                $given[$slug] = true;
            }
        }

        return array_values(array_filter(self::slugs(), fn (string $slug): bool => isset($given[$slug])));
    }

    /**
     * Fachrichtungen zu Slugs in Config-Reihenfolge.
     *
     * @param  iterable<mixed>  $slugs
     * @return list<Specialty>
     */
    public static function findMany(iterable $slugs): array
    {
        $wanted = self::normalize($slugs);

        return array_values(array_filter(
            self::all(),
            fn (Specialty $specialty): bool => in_array($specialty->slug, $wanted, true),
        ));
    }
}
