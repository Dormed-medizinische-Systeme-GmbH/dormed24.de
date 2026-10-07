<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function imageFor(Product $product, int $sortOrder): ProductImage
    {
        $path = "products/{$product->id}/image-{$sortOrder}.png";
        Storage::disk('public')->put($path, 'fake-image');

        return $product->images()->create(['path' => $path, 'sort_order' => $sortOrder]);
    }

    public function test_guests_cannot_upload_delete_or_reorder_images(): void
    {
        $product = Product::factory()->create();
        $image = $this->imageFor($product, 0);

        $this->post(route('admin.products.images.store', $product), [
            'image' => UploadedFile::fake()->image('a.jpg'),
        ])->assertRedirect(route('admin.login'));

        $this->delete(route('admin.products.images.destroy', [$product, $image]))
            ->assertRedirect(route('admin.login'));

        $this->patch(route('admin.products.images.reorder', $product), ['ids' => [$image->id]])
            ->assertRedirect(route('admin.login'));

        $this->assertDatabaseHas('product_images', ['id' => $image->id]);
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_admin_can_upload_an_image(): void
    {
        $product = Product::factory()->create();

        $this->actingAsAdmin()
            ->post(route('admin.products.images.store', $product), [
                'image' => UploadedFile::fake()->image('cover.jpg'),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $image = $product->images()->sole();

        $this->assertSame(0, $image->sort_order);
        $this->assertStringStartsWith("products/{$product->id}/", $image->path);
        Storage::disk('public')->assertExists($image->path);
    }

    public function test_further_uploads_are_appended_to_the_end(): void
    {
        $product = Product::factory()->create();
        $this->imageFor($product, 0);

        $this->actingAsAdmin()
            ->post(route('admin.products.images.store', $product), [
                'image' => UploadedFile::fake()->image('second.png'),
            ])
            ->assertRedirect();

        $this->assertSame([0, 1], $product->images()->pluck('sort_order')->all());
    }

    public function test_upload_rejects_non_images_and_unsupported_formats(): void
    {
        $product = Product::factory()->create();

        foreach ([
            UploadedFile::fake()->create('dokument.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->create('skript.php', 1, 'text/x-php'),
            UploadedFile::fake()->image('grafik.gif'),
        ] as $file) {
            $this->actingAsAdmin()
                ->post(route('admin.products.images.store', $product), ['image' => $file])
                ->assertSessionHasErrors('image');
        }

        $this->assertSame(0, $product->images()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_upload_requires_a_file(): void
    {
        $product = Product::factory()->create();

        $this->actingAsAdmin()
            ->post(route('admin.products.images.store', $product), [])
            ->assertSessionHasErrors('image');
    }

    public function test_upload_rejects_files_larger_than_five_megabytes(): void
    {
        $product = Product::factory()->create();

        $this->actingAsAdmin()
            ->post(route('admin.products.images.store', $product), [
                'image' => UploadedFile::fake()->image('riesig.jpg')->size(5121),
            ])
            ->assertSessionHasErrors('image');

        $this->assertSame(0, $product->images()->count());
    }

    public function test_upload_accepts_a_file_of_exactly_five_megabytes(): void
    {
        $product = Product::factory()->create();

        $this->actingAsAdmin()
            ->post(route('admin.products.images.store', $product), [
                'image' => UploadedFile::fake()->image('grenzfall.jpg')->size(5120),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $product->images()->count());
    }

    public function test_upload_is_refused_when_the_product_already_has_five_images(): void
    {
        $product = Product::factory()->create();

        foreach (range(0, 4) as $order) {
            $this->imageFor($product, $order);
        }

        $this->actingAsAdmin()
            ->post(route('admin.products.images.store', $product), [
                'image' => UploadedFile::fake()->image('sechstes.jpg'),
            ])
            ->assertSessionHasErrors(['image' => 'Maximal 5 Bilder pro Produkt erlaubt.']);

        $this->assertSame(5, $product->images()->count());
        $this->assertCount(5, Storage::disk('public')->allFiles());
    }

    public function test_deleting_an_image_removes_file_and_row_and_closes_the_gap(): void
    {
        $product = Product::factory()->create();
        $first = $this->imageFor($product, 0);
        $second = $this->imageFor($product, 1);
        $third = $this->imageFor($product, 2);

        $this->actingAsAdmin()
            ->delete(route('admin.products.images.destroy', [$product, $second]))
            ->assertRedirect()
            ->assertSessionHas('success');

        Storage::disk('public')->assertMissing($second->path);
        Storage::disk('public')->assertExists($first->path);
        Storage::disk('public')->assertExists($third->path);
        $this->assertDatabaseMissing('product_images', ['id' => $second->id]);
        $this->assertSame(0, $first->fresh()->sort_order);
        $this->assertSame(1, $third->fresh()->sort_order);
    }

    public function test_an_image_of_another_product_cannot_be_deleted(): void
    {
        $product = Product::factory()->create();
        $otherProduct = Product::factory()->create();
        $foreignImage = $this->imageFor($otherProduct, 0);

        $this->actingAsAdmin()
            ->delete(route('admin.products.images.destroy', [$product, $foreignImage]))
            ->assertForbidden();

        $this->assertDatabaseHas('product_images', ['id' => $foreignImage->id]);
        Storage::disk('public')->assertExists($foreignImage->path);
    }

    public function test_admin_can_reorder_images(): void
    {
        $product = Product::factory()->create();
        $first = $this->imageFor($product, 0);
        $second = $this->imageFor($product, 1);
        $third = $this->imageFor($product, 2);

        $this->actingAsAdmin()
            ->patch(route('admin.products.images.reorder', $product), [
                'ids' => [$third->id, $first->id, $second->id],
            ])
            ->assertRedirect();

        $this->assertSame(0, $third->fresh()->sort_order);
        $this->assertSame(1, $first->fresh()->sort_order);
        $this->assertSame(2, $second->fresh()->sort_order);
    }

    public function test_reorder_with_a_foreign_image_id_is_forbidden_and_changes_nothing(): void
    {
        $product = Product::factory()->create();
        $first = $this->imageFor($product, 0);
        $second = $this->imageFor($product, 1);
        $foreign = $this->imageFor(Product::factory()->create(), 0);

        $this->actingAsAdmin()
            ->patch(route('admin.products.images.reorder', $product), [
                'ids' => [$second->id, $foreign->id, $first->id],
            ])
            ->assertForbidden();

        $this->assertSame(0, $first->fresh()->sort_order);
        $this->assertSame(1, $second->fresh()->sort_order);
        $this->assertSame(0, $foreign->fresh()->sort_order);
    }

    public function test_reorder_validates_the_id_list(): void
    {
        $product = Product::factory()->create();

        $this->actingAsAdmin()
            ->patch(route('admin.products.images.reorder', $product), [])
            ->assertSessionHasErrors('ids');

        $this->actingAsAdmin()
            ->patch(route('admin.products.images.reorder', $product), ['ids' => ['abc']])
            ->assertSessionHasErrors('ids.0');
    }
}
