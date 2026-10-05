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
        /** @var list<array{slug: string, name: string, description?: string|null}> $entries */
        $entries = (array) config('categories', []);

        return array_map(
            fn (array $entry): Category => new Category(
                slug: $entry['slug'],
                name: $entry['name'],
                description: $entry['description'] ?? null,
            ),
            array_values($entries),
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
