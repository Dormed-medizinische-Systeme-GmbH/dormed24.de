<?php

namespace Tests\Feature\Shop;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_url_is_category_and_name_slug(): void
    {
        $product = Product::factory()->create([
            'name' => 'Tragbares Ultraschallsystem MU7',
            'category_slug' => 'ultraschallsysteme',
        ]);

        $this->assertSame('tragbares-ultraschallsystem-mu7', $product->slug);
        $this->assertSame(url('/ultraschallsysteme/tragbares-ultraschallsystem-mu7'), $product->url());
    }

    public function test_slug_is_lowercase_with_umlauts_written_out(): void
    {
        $this->assertSame('groesse-roentgenfilm-fuer-kinder', Product::slugFor('Größe Röntgenfilm für Kinder'));
        $this->assertSame('sonde-c5-25-mhz', Product::slugFor('Sonde C5 (2,5 MHz)'));
        $this->assertSame('', Product::slugFor('!!!'));
    }

    public function test_slug_is_created_once_and_does_not_follow_later_name_changes(): void
    {
        $product = Product::factory()->create(['name' => 'Erster Name']);

        $product->update(['name' => 'Zweiter Name']);

        $this->assertSame('erster-name', $product->refresh()->slug);
    }

    public function test_same_name_gets_a_numbered_slug_as_safety_net(): void
    {
        $first = Product::factory()->create(['name' => 'Gleicher Name']);
        $second = Product::factory()->create(['name' => 'Gleicher Name']);
        $third = Product::factory()->create(['name' => 'Gleicher Name']);

        $this->assertSame('gleicher-name', $first->slug);
        $this->assertSame('gleicher-name-2', $second->slug);
        $this->assertSame('gleicher-name-3', $third->slug);
    }

    public function test_product_without_category_has_no_url(): void
    {
        $product = Product::factory()->create(['category_slug' => null]);

        $this->assertNull($product->url());
    }

    public function test_product_page_opens_under_its_category(): void
    {
        $product = Product::factory()->create(['name' => 'Sonde C5', 'category_slug' => 'ultraschallsonden']);

        $this->get('/ultraschallsonden/sonde-c5')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Products/Show')
                    ->where('product.id', $product->id)
                    ->where('product.slug', 'sonde-c5')
                    ->where('product.category_slug', 'ultraschallsonden')
            );
    }

    public function test_product_page_is_not_found_under_a_different_category(): void
    {
        Product::factory()->create(['name' => 'Sonde C5', 'category_slug' => 'ultraschallsonden']);

        $this->get('/zubehoer/sonde-c5')->assertNotFound();
    }

    public function test_product_page_is_not_found_under_an_unknown_category(): void
    {
        Product::factory()->create(['name' => 'Sonde C5']);

        $this->get('/gibt-es-nicht/sonde-c5')->assertNotFound();
    }

    public function test_product_without_category_is_not_reachable(): void
    {
        $product = Product::factory()->create(['name' => 'Ohne Kategorie', 'category_slug' => null]);

        $this->get('/ultraschallsysteme/'.$product->slug)->assertNotFound();
    }

    public function test_old_product_urls_are_gone_without_redirect(): void
    {
        $product = Product::factory()->create();

        $this->get('/products')->assertNotFound();
        $this->get('/products/'.$product->id)->assertNotFound();
        $this->get('/products/search?q=a')->assertNotFound();
    }

    public function test_fixed_pages_are_not_swallowed_by_the_category_patterns(): void
    {
        $this->get('/search')->assertOk();
        $this->get(route('faq'))->assertOk();
        $this->get(route('impressum'))->assertOk();
        $this->get(route('sitemap'))->assertOk();
    }
}
