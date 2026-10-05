<?php

namespace App\Support\Catalog;

/**
 * Eine Produktkategorie aus config/categories.php (kein Eloquent-Model).
 */
final readonly class Category
{
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $description = null,
        public bool $hasSpecialties = false,
    ) {}

    /**
     * @return array{slug: string, name: string, description: string|null}
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
        ];
    }
}
