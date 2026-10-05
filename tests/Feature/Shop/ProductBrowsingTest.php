<?php

namespace Tests\Feature\Shop;

use App\Models\Manufacturer;
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

    public function test_index_exposes_manufacturer_filter_options_with_counts(): void
    {
        $sony = Manufacturer::factory()->create(['name' => 'Sony']);
        $mindray = Manufacturer::factory()->create(['name' => 'Mindray']);
        Product::factory()->count(2)->for($sony)->create();
        Product::factory()->for($mindray)->create();
        Product::factory()->for($mindray)->create(['is_available' => false]);
        Product::factory()->create(['manufacturer_id' => null]);

        $this->get(route('products.index'))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('filters.manufacturers', [])
                    ->where('filterOptions.total', 4)
                    ->has('filterOptions.manufacturers', 2)
                    ->where('filterOptions.manufacturers.0', ['id' => $mindray->id, 'name' => 'Mindray', 'count' => 1])
                    ->where('filterOptions.manufacturers.1', ['id' => $sony->id, 'name' => 'Sony', 'count' => 2])
            );
    }

    public function test_index_filters_by_manufacturer(): void
    {
        $sony = Manufacturer::factory()->create();
        $mindray = Manufacturer::factory()->create();
        Product::factory()->count(2)->for($sony)->create();
        Product::factory()->for($mindray)->create();

        $this->get(route('products.index', ['hersteller' => [$sony->id]]))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('total', 2)
                    ->where('filters.manufacturers', [$sony->id])
                    ->where('filterOptions.total', 3)
                    ->has('filterOptions.manufacturers', 2)
            );
    }

    public function test_index_combines_multiple_manufacturers_with_or(): void
    {
        $sony = Manufacturer::factory()->create();
        $mindray = Manufacturer::factory()->create();
        Product::factory()->for($sony)->create();
        Product::factory()->for($mindray)->create();
        Product::factory()->create();

        $this->get(route('products.index', ['hersteller' => [$sony->id, $mindray->id]]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('total', 2));
    }

    public function test_index_ignores_invalid_and_foreign_manufacturer_ids(): void
    {
        $sony = Manufacturer::factory()->create();
        $withoutProducts = Manufacturer::factory()->create();
        Product::factory()->count(2)->for($sony)->create();

        $this->get(route('products.index', ['hersteller' => ['abc', '-1', $withoutProducts->id, 999999]]))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('total', 2)
                    ->where('filters.manufacturers', [])
            );
    }

    public function test_index_ignores_scalar_manufacturer_parameter_format(): void
    {
        $sony = Manufacturer::factory()->create();
        Product::factory()->for($sony)->create();
        Product::factory()->create();

        $this->get(route('products.index', ['hersteller' => (string) $sony->id]))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('total', 1)
                    ->where('filters.manufacturers', [$sony->id])
            );
    }

    public function test_index_manufacturer_options_follow_the_search_query(): void
    {
        $sony = Manufacturer::factory()->create();
        $mindray = Manufacturer::factory()->create();
        Product::factory()->for($sony)->create(['name' => 'Druckerpapier UPP-110S']);
        Product::factory()->for($mindray)->create(['name' => 'Ultraschallsystem MU7']);

        $this->get(route('products.index', ['q' => 'Druckerpapier']))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->has('filterOptions.manufacturers', 1)
                    ->where('filterOptions.manufacturers.0.id', $sony->id)
                    ->where('filterOptions.total', 1)
            );
    }

    public function test_index_exposes_specialty_options_in_config_order_with_counts(): void
    {
        Product::factory()->create(['category_slug' => 'ultraschallsysteme', 'specialties' => ['urologie', 'kardiologie']]);
        Product::factory()->create(['category_slug' => 'ultraschallsonden', 'specialties' => ['kardiologie']]);
        Product::factory()->create(['category_slug' => 'zubehoer']);

        $this->get(route('products.index'))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('filters.specialties', [])
                    ->where('filterOptions.specialties', [
                        ['id' => 'kardiologie', 'name' => 'Kardiologie', 'count' => 2],
                        ['id' => 'urologie', 'name' => 'Urologie', 'count' => 1],
                    ])
                    ->where('filterOptions.total', 3)
                    ->where('filterOptions.totalWithSpecialtyFilter', 3)
            );
    }

    public function test_index_filters_by_specialty_with_or_within_the_group(): void
    {
        Product::factory()->create(['specialties' => ['kardiologie']]);
        Product::factory()->create(['specialties' => ['urologie', 'radiologie']]);
        Product::factory()->create(['specialties' => ['paediatrie']]);
        Product::factory()->create();

        $this->get(route('products.index', ['fachrichtung' => ['kardiologie', 'urologie']]))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('total', 2)
                    ->where('filters.specialties', ['kardiologie', 'urologie'])
                    ->where('filterOptions.totalWithSpecialtyFilter', 2)
            );
    }

    public function test_index_ignores_unknown_or_unused_specialties(): void
    {
        Product::factory()->count(2)->create(['specialties' => ['kardiologie']]);

        $this->get(route('products.index', ['fachrichtung' => ['zahnmedizin', 'urologie']]))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('total', 2)
                    ->where('filters.specialties', [])
            );
    }

    public function test_specialty_and_manufacturer_filters_combine_and_facet_each_other(): void
    {
        $sony = Manufacturer::factory()->create(['name' => 'Sony']);
        $mindray = Manufacturer::factory()->create(['name' => 'Mindray']);

        Product::factory()->for($sony)->create(['specialties' => ['kardiologie']]);
        Product::factory()->for($sony)->create(['specialties' => ['urologie']]);
        Product::factory()->for($mindray)->create(['specialties' => ['kardiologie']]);

        $this->get(route('products.index', ['hersteller' => [$sony->id], 'fachrichtung' => ['kardiologie']]))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('total', 1)
                    // Hersteller-Zahlen mit Fachrichtungs-Filter, Fachrichtungs-Zahlen mit Hersteller-Filter.
                    ->where('filterOptions.manufacturers.0', ['id' => $mindray->id, 'name' => 'Mindray', 'count' => 1])
                    ->where('filterOptions.manufacturers.1', ['id' => $sony->id, 'name' => 'Sony', 'count' => 1])
                    ->where('filterOptions.specialties', [
                        ['id' => 'kardiologie', 'name' => 'Kardiologie', 'count' => 1],
                        ['id' => 'urologie', 'name' => 'Urologie', 'count' => 1],
                    ])
                    ->where('filterOptions.total', 3)
                    ->where('filterOptions.totalWithSpecialtyFilter', 2)
            );
    }

    public function test_manufacturer_without_matches_for_the_specialty_stays_selectable_with_zero(): void
    {
        $sony = Manufacturer::factory()->create(['name' => 'Sony']);
        $mindray = Manufacturer::factory()->create(['name' => 'Mindray']);

        Product::factory()->for($sony)->create(['specialties' => ['kardiologie']]);
        Product::factory()->for($mindray)->create(['specialties' => ['urologie']]);

        $this->get(route('products.index', ['hersteller' => [$mindray->id], 'fachrichtung' => ['kardiologie']]))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('total', 0)
                    ->where('filters.manufacturers', [$mindray->id])
                    ->where('filterOptions.manufacturers.0', ['id' => $mindray->id, 'name' => 'Mindray', 'count' => 0])
            );
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

    public function test_show_exposes_technical_specs_in_order(): void
    {
        $product = Product::factory()->create([
            'technical_specs' => [
                ['label' => 'Gewicht', 'value' => '2,4 kg'],
                ['label' => 'Frequenz', 'value' => '2–5 MHz'],
            ],
        ]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('product.technical_specs', [
                        ['label' => 'Gewicht', 'value' => '2,4 kg'],
                        ['label' => 'Frequenz', 'value' => '2–5 MHz'],
                    ])
            );
    }

    public function test_show_exposes_empty_technical_specs_when_none_are_set(): void
    {
        $product = Product::factory()->create();

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('product.technical_specs', []));
    }

    public function test_show_exposes_specialty_names_in_config_order(): void
    {
        $product = Product::factory()->create([
            'category_slug' => 'ultraschallsysteme',
            'specialties' => ['urologie', 'kardiologie'],
        ]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page->where('product.specialties', [
                    ['slug' => 'kardiologie', 'name' => 'Kardiologie'],
                    ['slug' => 'urologie', 'name' => 'Urologie'],
                ])
            );
    }

    public function test_show_returns_404_for_unknown_product(): void
    {
        $this->get('/products/999999')->assertNotFound();
    }

    public function test_show_lists_similar_products_from_the_same_category(): void
    {
        $product = Product::factory()->create(['category_slug' => 'zubehoer']);
        $sameCategoryProduct = Product::factory()->create(['category_slug' => 'zubehoer']);
        Product::factory()->create(['category_slug' => 'verbrauchsartikel']);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('product.category', ['slug' => 'zubehoer', 'name' => 'Zubehör', 'description' => null])
                    ->has('similarProducts', 1)
                    ->where('similarProducts.0.id', $sameCategoryProduct->id)
            );
    }

    public function test_show_similar_products_excludes_the_current_product(): void
    {
        $product = Product::factory()->create(['category_slug' => 'zubehoer']);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('similarProducts', 0));
    }

    public function test_show_returns_no_similar_products_without_a_category(): void
    {
        $product = Product::factory()->create(['category_slug' => null]);
        Product::factory()->create(['category_slug' => null]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('similarProducts', 0));
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
