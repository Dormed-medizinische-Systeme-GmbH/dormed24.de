<?php

namespace Tests\Feature\Shop;

use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_page_lists_only_its_products(): void
    {
        Product::factory()->count(2)->create(['category_slug' => 'zubehoer']);
        Product::factory()->create(['category_slug' => 'verbrauchsartikel']);
        Product::factory()->create(['category_slug' => null]);

        $this->get(route('category.show', 'zubehoer'))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Products/ByCategory')
                    ->where('category.slug', 'zubehoer')
                    ->where('category.name', 'Zubehör')
                    ->where('total', 2)
            );
    }

    public function test_category_comes_from_config_not_from_the_database(): void
    {
        config(['categories' => [
            ['slug' => 'neue-gruppe', 'name' => 'Neue Gruppe', 'description' => 'Beschreibung aus der Config'],
        ]]);

        $this->get(route('category.show', 'neue-gruppe'))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('category', [
                        'slug' => 'neue-gruppe',
                        'name' => 'Neue Gruppe',
                        'description' => 'Beschreibung aus der Config',
                    ])
                    ->where('total', 0)
            );
    }

    public function test_ultraschallsonden_category_exists(): void
    {
        $this->get(route('category.show', 'ultraschallsonden'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('category.name', 'Ultraschallsonden'));
    }

    public function test_category_page_excludes_unavailable_products(): void
    {
        Product::factory()->create(['category_slug' => 'zubehoer', 'is_available' => true]);
        Product::factory()->create(['category_slug' => 'zubehoer', 'is_available' => false]);

        $this->get(route('category.show', 'zubehoer'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('total', 1));
    }

    public function test_category_page_accepts_sort_option(): void
    {
        $this->get(route('category.show', ['category' => 'zubehoer', 'sort' => 'price_asc']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('sort', 'price_asc'));
    }

    public function test_category_page_filters_by_manufacturer_within_the_category(): void
    {
        $sony = Manufacturer::factory()->create(['name' => 'Sony']);
        $mindray = Manufacturer::factory()->create(['name' => 'Mindray']);
        Product::factory()->for($sony)->create(['category_slug' => 'zubehoer']);
        Product::factory()->for($mindray)->create(['category_slug' => 'zubehoer']);
        Product::factory()->for($sony)->create(['category_slug' => 'verbrauchsartikel']);

        $this->get(route('category.show', ['category' => 'zubehoer', 'hersteller' => [$sony->id]]))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Products/ByCategory')
                    ->where('total', 1)
                    ->where('filters.manufacturers', [$sony->id])
                    ->where('filterOptions.total', 2)
                    ->where('filterOptions.manufacturers.0', ['id' => $mindray->id, 'name' => 'Mindray', 'count' => 1])
                    ->where('filterOptions.manufacturers.1', ['id' => $sony->id, 'name' => 'Sony', 'count' => 1])
            );
    }

    public function test_category_page_only_offers_manufacturers_of_its_products(): void
    {
        $sony = Manufacturer::factory()->create();
        $foreign = Manufacturer::factory()->create();
        Product::factory()->for($sony)->create(['category_slug' => 'zubehoer']);
        Product::factory()->for($foreign)->create(['category_slug' => 'verbrauchsartikel']);

        $this->get(route('category.show', ['category' => 'zubehoer', 'hersteller' => [$foreign->id]]))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('total', 1)
                    ->where('filters.manufacturers', [])
                    ->has('filterOptions.manufacturers', 1)
                    ->where('filterOptions.manufacturers.0.id', $sony->id)
            );
    }

    public function test_unknown_category_returns_404(): void
    {
        $this->get('/gibt-es-nicht')->assertNotFound();
    }

    public function test_navigation_lists_configured_categories_in_config_order(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page->where('navCategories', [
                    ['slug' => 'ultraschallsysteme', 'name' => 'Ultraschallsysteme'],
                    ['slug' => 'ultraschallsonden', 'name' => 'Ultraschallsonden'],
                    ['slug' => 'verbrauchsartikel', 'name' => 'Verbrauchsartikel'],
                    ['slug' => 'zubehoer', 'name' => 'Zubehör'],
                ])->etc()
            );
    }

    public function test_category_page_offers_specialties_only_where_products_have_them(): void
    {
        Product::factory()->create(['category_slug' => 'ultraschallsysteme', 'specialties' => ['kardiologie']]);
        Product::factory()->create(['category_slug' => 'zubehoer']);

        $this->get('/ultraschallsysteme')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('filterOptions.specialties', 1));

        $this->get('/zubehoer')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('filterOptions.specialties', []));
    }

    public function test_category_page_filters_by_specialty(): void
    {
        Product::factory()->create(['category_slug' => 'ultraschallsonden', 'specialties' => ['kardiologie']]);
        Product::factory()->create(['category_slug' => 'ultraschallsonden', 'specialties' => ['urologie']]);

        $this->get('/ultraschallsonden?fachrichtung[]=urologie')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('total', 1)->where('filters.specialties', ['urologie']));
    }
}
