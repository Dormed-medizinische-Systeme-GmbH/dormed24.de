<?php

namespace Tests\Feature\Shop;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_page_lists_matching_products(): void
    {
        Product::factory()->create(['name' => 'Beatmungsgerät Pro']);
        Product::factory()->create(['name' => 'Infusionspumpe']);

        $this->get(route('search', ['q' => 'Beatmung']))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Products/Search')
                    ->where('total', 1)
                    ->where('query', 'Beatmung')
                    ->where('products.data.0.name', 'Beatmungsgerät Pro')
                    ->where('products.data.0.slug', 'beatmungsgeraet-pro')
                    ->where('products.data.0.category_slug', 'ultraschallsysteme')
            );
    }

    public function test_search_page_without_query_shows_no_products(): void
    {
        Product::factory()->count(3)->create();

        $this->get(route('search'))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Products/Search')
                    ->where('total', 0)
                    ->where('query', '')
            );
    }

    public function test_search_page_excludes_unavailable_products(): void
    {
        Product::factory()->create(['name' => 'Maske Standard', 'is_available' => true]);
        Product::factory()->create(['name' => 'Maske Deluxe', 'is_available' => false]);

        $this->get(route('search', ['q' => 'Maske']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('total', 1));
    }

    public function test_search_page_accepts_sort_option(): void
    {
        $this->get(route('search', ['q' => 'a', 'sort' => 'price_desc']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('sort', 'price_desc'));
    }

    public function test_search_page_treats_like_wildcards_literally(): void
    {
        Product::factory()->create(['name' => 'Beatmungsgerät Pro']);

        $this->get(route('search', ['q' => '%']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('total', 0));
    }

    public function test_search_answers_json_for_the_header_search_with_product_links(): void
    {
        Product::factory()->create(['name' => 'Sauerstoffgerät', 'category_slug' => 'zubehoer']);

        $this->getJson(route('search', ['q' => 'Sauerstoff']))
            ->assertOk()
            ->assertHeader('Vary', 'Accept')
            ->assertJsonPath('results.0.slug', 'sauerstoffgeraet')
            ->assertJsonPath('results.0.category_slug', 'zubehoer');
    }

    public function test_search_hides_products_without_category(): void
    {
        Product::factory()->create(['name' => 'Verwaist', 'category_slug' => null]);

        $this->getJson(route('search', ['q' => 'Verwaist']))
            ->assertOk()
            ->assertExactJson(['results' => [], 'total' => 0]);
    }
}
