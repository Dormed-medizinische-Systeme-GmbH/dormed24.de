<?php

namespace App\Support\Catalog;

/**
 * Zugriff auf die festen Produktkategorien aus config/categories.php.
 */
final class Categories
{
    /**
     * Alle Kategorien in der konfigurierten Reihenfolge.
     *
     * @return list<Category>
     */
    public static function all(): array
    {
        /** @var list<array{slug: string, name: string, description?: string|null, has_specialties?: bool}> $entries */
        $entries = (array) config('categories', []);

        return array_map(
            fn (array $entry): Category => new Category(
                slug: $entry['slug'],
                name: $entry['name'],
                description: $entry['description'] ?? null,
                hasSpecialties: $entry['has_specialties'] ?? false,
            ),
            $entries,
        );
    }

    public static function find(?string $slug): ?Category
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        foreach (self::all() as $category) {
            if ($category->slug === $slug) {
                return $category;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_map(fn (Category $category): string => $category->slug, self::all());
    }

    /**
     * Kategorien, deren Produkte Fachrichtungen haben.
     *
     * @return list<string>
     */
    public static function slugsWithSpecialties(): array
    {
        return array_values(array_map(
            fn (Category $category): string => $category->slug,
            array_filter(self::all(), fn (Category $category): bool => $category->hasSpecialties),
        ));
    }

    /**
     * Für Navigation und Dropdowns.
     *
     * @return list<array{slug: string, name: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (Category $category): array => ['slug' => $category->slug, 'name' => $category->name],
            self::all(),
        );
    }
}
