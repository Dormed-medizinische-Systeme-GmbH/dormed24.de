<?php

namespace Tests\Feature\Shop;

use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestLandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_sortiment_gets_complete_products_for_the_product_card(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Mindray']);
        Product::factory()->create([
            'name' => 'Blutdruckmessgerät',
            'category_slug' => 'zubehoer',
            'manufacturer_id' => $manufacturer->id,
        ]);
        Product::factory()->create(['category_slug' => null]);

        $this->get(route('test.hover-header'))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Test/Welcome')
                    ->has('randomProducts', 1)
                    ->where('randomProducts.0.slug', 'blutdruckmessgeraet')
                    ->where('randomProducts.0.category_slug', 'zubehoer')
                    ->where('randomProducts.0.manufacturer.name', 'Mindray')
                    ->has('randomProducts.0.images')
            );
    }
}
