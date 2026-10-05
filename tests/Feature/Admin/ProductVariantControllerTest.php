<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_requires_admin(): void
    {
        $product = Product::factory()->create();

        $this->post(route('admin.products.variants.store', $product), [
            'label' => '8er-Pack',
            'price' => '39.99',
        ])->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_add_variant(): void
    {
        $product = Product::factory()->create();

        $this->actingAsAdmin()
            ->post(route('admin.products.variants.store', $product), [
                'label' => '8er-Pack',
                'price' => '39.99',
                'is_default' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'label' => '8er-Pack',
            'price' => '39.99',
        ]);
    }

    public function test_adding_default_variant_clears_other_defaults(): void
    {
        $product = Product::factory()->create();
        $existing = ProductVariant::factory()->for($product)->create(['is_default' => true]);

        $this->actingAsAdmin()
            ->post(route('admin.products.variants.store', $product), [
                'label' => '12er-Pack',
                'price' => '59.99',
                'is_default' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('product_variants', ['id' => $existing->id, 'is_default' => false]);
        $this->assertDatabaseHas('product_variants', ['label' => '12er-Pack', 'is_default' => true]);
    }

    public function test_admin_can_update_variant(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create(['label' => '1er-Pack', 'price' => '9.99']);

        $this->actingAsAdmin()
            ->put(route('admin.products.variants.update', [$product, $variant]), [
                'label' => '1er-Pack (neu)',
                'price' => '12.00',
                'is_default' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'label' => '1er-Pack (neu)',
            'price' => '12.00',
        ]);
    }

    public function test_cannot_update_variant_of_other_product(): void
    {
        $product = Product::factory()->create();
        $other = Product::factory()->create();
        $variant = ProductVariant::factory()->for($other)->create();

        $this->actingAsAdmin()
            ->put(route('admin.products.variants.update', [$product, $variant]), [
                'label' => 'Hacked',
                'price' => '1.00',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_delete_variant(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();

        $this->actingAsAdmin()
            ->delete(route('admin.products.variants.destroy', [$product, $variant]))
            ->assertRedirect();

        $this->assertDatabaseMissing('product_variants', ['id' => $variant->id]);
    }

    public function test_cannot_delete_variant_of_other_product(): void
    {
        $product = Product::factory()->create();
        $other = Product::factory()->create();
        $variant = ProductVariant::factory()->for($other)->create();

        $this->actingAsAdmin()
            ->delete(route('admin.products.variants.destroy', [$product, $variant]))
            ->assertForbidden();
    }

    public function test_store_validates_required_fields(): void
    {
        $product = Product::factory()->create();

        $this->actingAsAdmin()
            ->post(route('admin.products.variants.store', $product), [])
            ->assertSessionHasErrors(['label', 'price']);
    }
}
