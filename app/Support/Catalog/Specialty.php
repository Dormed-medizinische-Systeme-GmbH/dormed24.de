<?php

namespace App\Support\Catalog;

/**
 * Eine Fachrichtung aus config/specialties.php (kein Eloquent-Model).
 */
final readonly class Specialty
{
    public function __construct(
        public string $slug,
        public string $name,
    ) {}

    /**
     * @return array{slug: string, name: string}
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
        ];
    }
}
