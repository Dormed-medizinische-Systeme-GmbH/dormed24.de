<?php

namespace Tests\Feature\Shop;

use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_page_lists_only_its_products(): void
    {
        $category = Category::factory()->create();
        Product::factory()->count(2)->for($category)->create();
        Product::factory()->create();

        $this->get(route('category.show', $category))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Products/ByCategory')
                    ->where('category.id', $category->id)
                    ->where('category.slug', $category->slug)
                    ->where('total', 2)
            );
    }

    public function test_category_page_excludes_unavailable_products(): void
    {
        $category = Category::factory()->create();
        Product::factory()->for($category)->create(['is_available' => true]);
        Product::factory()->for($category)->create(['is_available' => false]);

        $this->get(route('category.show', $category))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('total', 1));
    }

    public function test_category_page_accepts_sort_option(): void
    {
        $category = Category::factory()->create();

        $this->get(route('category.show', ['category' => $category->slug, 'sort' => 'price_asc']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('sort', 'price_asc'));
    }

    public function test_category_page_filters_by_manufacturer_within_the_category(): void
    {
        $category = Category::factory()->create();
        $sony = Manufacturer::factory()->create(['name' => 'Sony']);
        $mindray = Manufacturer::factory()->create(['name' => 'Mindray']);
        Product::factory()->for($category)->for($sony)->create();
        Product::factory()->for($category)->for($mindray)->create();
        Product::factory()->for($sony)->create();

        $this->get(route('category.show', ['category' => $category->slug, 'hersteller' => [$sony->id]]))
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
        $category = Category::factory()->create();
        $sony = Manufacturer::factory()->create();
        $foreign = Manufacturer::factory()->create();
        Product::factory()->for($category)->for($sony)->create();
        Product::factory()->for($foreign)->create();

        $this->get(route('category.show', ['category' => $category->slug, 'hersteller' => [$foreign->id]]))
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
}
