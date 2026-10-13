<?php

namespace Tests\Feature\Console;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesImages;
use Tests\TestCase;

class ConvertProductImagesTest extends TestCase
{
    use MakesImages;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireImageEncoding();
        Storage::fake('public');
    }

    public function test_it_converts_legacy_images_and_updates_path_and_dimensions(): void
    {
        $product = Product::factory()->create();
        Storage::disk('public')->put('products/1/a.jpg', $this->imageBytes('jpeg', 50, 20));
        Storage::disk('public')->put('products/1/b.png', $this->imageBytes('png', 30, 30));
        $a = $product->images()->create(['path' => 'products/1/a.jpg', 'sort_order' => 0]);
        $b = $product->images()->create(['path' => 'products/1/b.png', 'sort_order' => 1]);

        $this->artisan('product-images:convert')
            ->expectsOutputToContain('2 umgewandelt, 0 bereits aktuell, 0 fehlgeschlagen')
            ->assertSuccessful();

        $a->refresh();
        $b->refresh();
        $this->assertSame(['products/1/a.webp', 50, 20], [$a->path, $a->width, $a->height]);
        $this->assertSame(['products/1/b.webp', 30, 30], [$b->path, $b->width, $b->height]);
        $this->assertEqualsCanonicalizing([
            'products/1/a.webp', 'products/1/a.avif',
            'products/1/b.webp', 'products/1/b.avif',
        ], Storage::disk('public')->allFiles());
    }

    public function test_a_second_run_changes_nothing(): void
    {
        $product = Product::factory()->create();
        Storage::disk('public')->put('products/1/a.jpg', $this->imageBytes('jpeg'));
        $product->images()->create(['path' => 'products/1/a.jpg', 'sort_order' => 0]);

        $this->artisan('product-images:convert')->assertSuccessful();
        $before = Storage::disk('public')->get('products/1/a.webp');

        $this->artisan('product-images:convert')
            ->expectsOutputToContain('0 umgewandelt, 1 bereits aktuell')
            ->assertSuccessful();

        $this->assertSame($before, Storage::disk('public')->get('products/1/a.webp'));
    }

    public function test_dry_run_touches_neither_files_nor_rows(): void
    {
        $product = Product::factory()->create();
        Storage::disk('public')->put('products/1/a.jpg', $this->imageBytes('jpeg'));
        $image = $product->images()->create(['path' => 'products/1/a.jpg', 'sort_order' => 0]);

        $this->artisan('product-images:convert --dry-run')
            ->expectsOutputToContain('1 umzuwandeln')
            ->assertSuccessful();

        $this->assertSame('products/1/a.jpg', $image->fresh()->path);
        $this->assertSame(['products/1/a.jpg'], Storage::disk('public')->allFiles());
    }

    public function test_broken_images_are_reported_and_left_alone(): void
    {
        $product = Product::factory()->create();
        Storage::disk('public')->put('products/1/kaputt.jpg', 'kein Bild');
        $product->images()->create(['path' => 'products/1/fehlt.png', 'sort_order' => 0]);
        $broken = $product->images()->create(['path' => 'products/1/kaputt.jpg', 'sort_order' => 1]);

        $this->artisan('product-images:convert')
            ->expectsOutputToContain('0 umgewandelt, 0 bereits aktuell, 2 fehlgeschlagen')
            ->assertFailed();

        $this->assertSame('products/1/kaputt.jpg', $broken->fresh()->path);
        Storage::disk('public')->assertExists('products/1/kaputt.jpg');
    }
}
