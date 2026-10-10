<?php

namespace Database\Factories;

use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'manufacturer_id' => Manufacturer::factory(),
            // Slug aus config/categories.php; jedes Produkt braucht eine Kategorie (Shop-URL).
            'category_slug' => 'ultraschallsysteme',
            'name' => fake()->words(3, true),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 5, 500),
        ];
    }
}
