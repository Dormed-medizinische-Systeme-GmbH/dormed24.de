<?php

namespace Tests\Feature\Admin;

use App\Models\Manufacturer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductEditTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): static
    {
        $admin = User::factory()->admin()->create();

        return $this->actingAs($admin, 'admin');
    }

    public function test_edit_page_requires_admin(): void
    {
        $product = Product::factory()->create();

        $this->get(route('admin.products.edit', $product))
            ->assertRedirect(route('admin.login'));
    }

    public function test_edit_page_renders_for_admin(): void
    {
        $product = Product::factory()->create();

        $this->actingAsAdmin()
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Admin/Products/Edit')
                    ->has('product', fn ($prop) => $prop->where('id', $product->id)->etc())
                    ->has('manufacturers')
            );
    }

    public function test_edit_page_includes_manufacturers(): void
    {
        $manufacturer = Manufacturer::factory()->create();
        $product = Product::factory()->for($manufacturer)->create();

        $this->actingAsAdmin()
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->has('manufacturers', 1)
                    ->where('manufacturers.0.id', $manufacturer->id)
            );
    }

    public function test_update_requires_admin(): void
    {
        $product = Product::factory()->create();

        $this->put(route('admin.products.update', $product), [
            'name' => 'New Name',
            'price' => '9.99',
        ])->assertRedirect(route('admin.login'));
    }

    public function test_product_can_be_updated(): void
    {
        $manufacturer = Manufacturer::factory()->create();
        $product = Product::factory()->create(['name' => 'Old Name', 'price' => '5.00']);

        $this->actingAsAdmin()
            ->put(route('admin.products.update', $product), [
                'name' => 'Updated Name',
                'description' => 'A new description.',
                'price' => '19.99',
                'manufacturer_id' => $manufacturer->id,
                'is_available' => true,
            ])
            ->assertRedirect(route('admin.products.index'));

        $product->refresh();
        $this->assertSame('Updated Name', $product->name);
        $this->assertEquals('19.99', $product->price);
        $this->assertSame($manufacturer->id, $product->manufacturer_id);
    }

    public function test_product_description_can_be_cleared(): void
    {
        $product = Product::factory()->create(['description' => 'Some description']);

        $this->actingAsAdmin()
            ->put(route('admin.products.update', $product), [
                'name' => $product->name,
                'description' => '',
                'price' => $product->price,
                'manufacturer_id' => null,
                'is_available' => true,
            ])
            ->assertRedirect(route('admin.products.index'));

        $product->refresh();
        $this->assertEmpty($product->description);
    }

    public function test_update_validates_required_fields(): void
    {
        $product = Product::factory()->create();

        $this->actingAsAdmin()
            ->put(route('admin.products.update', $product), [
                'name' => '',
                'price' => '',
            ])
            ->assertSessionHasErrors(['name', 'price']);
    }

    public function test_update_validates_manufacturer_exists(): void
    {
        $product = Product::factory()->create();

        $this->actingAsAdmin()
            ->put(route('admin.products.update', $product), [
                'name' => 'Test',
                'price' => '9.99',
                'manufacturer_id' => 99999,
            ])
            ->assertSessionHasErrors(['manufacturer_id']);
    }

    public function test_destroy_deletes_product_without_orders(): void
    {
        $product = Product::factory()->create();

        $this->actingAsAdmin()
            ->delete(route('admin.products.destroy', $product))
            ->assertRedirect(route('admin.products.index'));

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_destroy_removes_the_image_directory(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();
        Storage::disk('public')->put("products/{$product->id}/cover.png", 'fake-image');
        $product->images()->create(['path' => "products/{$product->id}/cover.png", 'sort_order' => 0]);

        $this->actingAsAdmin()
            ->delete(route('admin.products.destroy', $product))
            ->assertRedirect(route('admin.products.index'));

        Storage::disk('public')->assertDirectoryEmpty("products/{$product->id}");
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_destroy_refuses_product_that_was_ordered(): void
    {
        $product = Product::factory()->create();
        $order = Order::factory()->create();
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => $product->price,
            'quantity' => 1,
        ]);

        $this->actingAsAdmin()
            ->from(route('admin.products.index'))
            ->delete(route('admin.products.destroy', $product))
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }
}
