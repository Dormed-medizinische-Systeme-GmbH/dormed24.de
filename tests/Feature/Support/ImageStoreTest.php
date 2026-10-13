<?php

namespace Tests\Feature\Support;

use App\Support\Images\ImageConversionException;
use App\Support\Images\ImageStore;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesImages;
use Tests\TestCase;

class ImageStoreTest extends TestCase
{
    use MakesImages;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_avif_path_swaps_only_the_extension_of_webp_files(): void
    {
        $this->assertSame('products/1/a.avif', ImageStore::avifPath('products/1/a.webp'));
        $this->assertSame('products/1/a.avif', ImageStore::avifPath('products/1/a.WEBP'));
        $this->assertNull(ImageStore::avifPath('products/1/a.jpg'));
        $this->assertNull(ImageStore::avifPath('products/1/a.webp.jpg'));
    }

    public function test_store_writes_webp_and_avif_side_by_side_and_nothing_else(): void
    {
        $this->requireImageEncoding();

        $stored = app(ImageStore::class)->store($this->imageBytes('png', 40, 30), 'products/5', 'cover');

        $this->assertSame('products/5/cover.webp', $stored->path);
        $this->assertSame([40, 30], [$stored->width, $stored->height]);
        $this->assertEqualsCanonicalizing(
            ['products/5/cover.webp', 'products/5/cover.avif'],
            Storage::disk('public')->allFiles(),
        );
    }

    public function test_store_without_a_name_picks_a_random_one(): void
    {
        $this->requireImageEncoding();

        $first = app(ImageStore::class)->store($this->imageBytes(), 'products/5');
        $second = app(ImageStore::class)->store($this->imageBytes(), 'products/5');

        $this->assertNotSame($first->path, $second->path);
        $this->assertMatchesRegularExpression('#^products/5/[A-Za-z0-9]{40}\.webp$#', $first->path);
    }

    public function test_a_failed_conversion_leaves_no_files_behind(): void
    {
        $this->requireImageEncoding();

        try {
            app(ImageStore::class)->store('kein Bild', 'products/5');
            $this->fail('Erwartet: ImageConversionException');
        } catch (ImageConversionException) {
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    public function test_convert_existing_replaces_a_jpeg_with_the_pair(): void
    {
        $this->requireImageEncoding();
        Storage::disk('public')->put('products/5/alt.jpg', $this->imageBytes('jpeg', 60, 20));

        $stored = app(ImageStore::class)->convertExisting('products/5/alt.jpg');

        $this->assertSame('products/5/alt.webp', $stored->path);
        $this->assertSame([60, 20], [$stored->width, $stored->height]);
        $this->assertEqualsCanonicalizing(
            ['products/5/alt.webp', 'products/5/alt.avif'],
            Storage::disk('public')->allFiles(),
        );
    }

    public function test_convert_existing_keeps_a_small_webp_untouched_and_adds_the_avif(): void
    {
        $this->requireImageEncoding();
        $webp = $this->imageBytes('webp', 60, 20);
        Storage::disk('public')->put('products/5/neu.webp', $webp);

        $stored = app(ImageStore::class)->convertExisting('products/5/neu.webp');

        $this->assertSame('products/5/neu.webp', $stored->path);
        $this->assertSame($webp, Storage::disk('public')->get('products/5/neu.webp'));
        Storage::disk('public')->assertExists('products/5/neu.avif');
    }

    public function test_convert_existing_fails_for_a_missing_file(): void
    {
        $this->requireImageEncoding();

        $this->expectException(ImageConversionException::class);

        app(ImageStore::class)->convertExisting('products/5/fehlt.jpg');
    }

    public function test_delete_removes_both_formats(): void
    {
        Storage::disk('public')->put('products/5/a.webp', 'w');
        Storage::disk('public')->put('products/5/a.avif', 'a');
        Storage::disk('public')->put('products/5/b.webp', 'w');

        app(ImageStore::class)->delete('products/5/a.webp');

        $this->assertSame(['products/5/b.webp'], Storage::disk('public')->allFiles());
    }

    public function test_delete_of_a_legacy_file_only_removes_that_file(): void
    {
        Storage::disk('public')->put('products/5/a.jpg', 'j');
        Storage::disk('public')->put('products/5/a.avif', 'a');

        app(ImageStore::class)->delete('products/5/a.jpg');

        $this->assertSame(['products/5/a.avif'], Storage::disk('public')->allFiles());
    }
}
