<?php

namespace Tests\Feature\Shop;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductBrowsingTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_products(): void
    {
        Product::factory()->count(3)->create();

        $this->get(route('products.index'))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Products/Index')
                    ->where('total', 3)
                    ->where('sort', 'name_asc')
            );
    }

    public function test_app_url_is_shared_for_absolute_canonical_tags(): void
    {
        // Basis für Canonical-/og:url in AppHead.svelte — im SSR gibt es
        // kein window.location, og:url verlangt absolute URLs (V4 SEO-1).
        $this->get(route('products.index'))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page->where('appUrl', config('app.url'))->etc()
            );
    }

    public function test_index_filters_by_search_query(): void
    {
        Product::factory()->create(['name' => 'Beatmungsgerät Pro']);
        Product::factory()->create(['name' => 'Infusionspumpe']);

        $this->get(route('products.index', ['q' => 'Beatmung']))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Products/Index')
                    ->where('total', 1)
                    ->where('query', 'Beatmung')
            );
    }

    public function test_index_excludes_unavailable_products(): void
    {
        Product::factory()->create(['is_available' => true]);
        Product::factory()->create(['is_available' => false]);

        $this->get(route('products.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('total', 1));
    }

    public function test_search_treats_like_wildcards_literally(): void
    {
        Product::factory()->create(['name' => 'Beatmungsgerät Pro']);
        Product::factory()->create(['name' => 'Infusionspumpe']);

        // "%" darf nicht als LIKE-Platzhalter wirken (sonst kaemen alle
        // Produkte zurueck) — als literaler String matcht es nichts.
        $this->getJson(route('products.search', ['q' => '%']))
            ->assertOk()
            ->assertJson(['total' => 0, 'results' => []]);
    }

    public function test_search_finds_product_with_literal_percent_in_name(): void
    {
        Product::factory()->create(['name' => 'Rabatt 10% Set']);
        Product::factory()->create(['name' => 'Infusionspumpe']);

        $this->getJson(route('products.search', ['q' => '10%']))
            ->assertOk()
            ->assertJson(['total' => 1]);
    }

    public function test_index_accepts_price_sort(): void
    {
        Product::factory()->create();

        $this->get(route('products.index', ['sort' => 'price_desc']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('sort', 'price_desc'));
    }

    public function test_show_renders_product(): void
    {
        $product = Product::factory()->create();

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Products/Show')
                    ->where('product.id', $product->id)
                    ->has('ratings')
                    ->has('ratingSummary')
            );
    }

    /**
     * Bewusste Entscheidung (ANALYSE-V2, Businesslogik 1): Detailseiten nicht
     * verfügbarer Produkte bleiben erreichbar (SEO/Bookmarks); das Frontend
     * zeigt den "nicht verfügbar"-Zustand und JSON-LD meldet OutOfStock.
     */
    public function test_show_keeps_unavailable_products_reachable(): void
    {
        $product = Product::factory()->create(['is_available' => false]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Products/Show')
                    ->where('product.id', $product->id)
                    ->where('product.is_available', false)
            );
    }

    public function test_show_returns_404_for_unknown_product(): void
    {
        $this->get('/products/999999')->assertNotFound();
    }

    public function test_search_returns_matching_products_as_json(): void
    {
        Product::factory()->create(['name' => 'Sauerstoffgerät']);
        Product::factory()->create(['name' => 'Verbandmaterial']);

        $this->getJson(route('products.search', ['q' => 'Sauerstoff']))
            ->assertOk()
            ->assertJson(['total' => 1])
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.name', 'Sauerstoffgerät');
    }

    public function test_search_returns_empty_for_blank_query(): void
    {
        Product::factory()->create();

        $this->getJson(route('products.search', ['q' => '']))
            ->assertOk()
            ->assertExactJson(['results' => [], 'total' => 0]);
    }

    public function test_search_excludes_unavailable_products(): void
    {
        Product::factory()->create(['name' => 'Maske Standard', 'is_available' => true]);
        Product::factory()->create(['name' => 'Maske Deluxe', 'is_available' => false]);

        $this->getJson(route('products.search', ['q' => 'Maske']))
            ->assertOk()
            ->assertJson(['total' => 1])
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.name', 'Maske Standard');
    }

    public function test_search_limits_results_to_five(): void
    {
        Product::factory()->count(8)->create(['name' => 'Maske Standard']);

        $response = $this->getJson(route('products.search', ['q' => 'Maske']))
            ->assertOk()
            ->assertJson(['total' => 8]);

        $this->assertCount(5, $response->json('results'));
    }
}
