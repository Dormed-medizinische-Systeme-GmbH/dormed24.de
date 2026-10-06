<?php

namespace Tests\Feature\Admin;

use App\Models\Manufacturer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductEditTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_edit_page_offers_configured_categories(): void
    {
        $product = Product::factory()->create(['category_slug' => 'ultraschallsonden']);

        $this->actingAsAdmin()
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('product.category_slug', 'ultraschallsonden')
                    ->where('categories', [
                        ['slug' => 'ultraschallsysteme', 'name' => 'Ultraschallsysteme'],
                        ['slug' => 'ultraschallsonden', 'name' => 'Ultraschallsonden'],
                        ['slug' => 'verbrauchsartikel', 'name' => 'Verbrauchsartikel'],
                        ['slug' => 'zubehoer', 'name' => 'Zubehör'],
                    ])
            );
    }

    public function test_product_category_can_be_set_to_a_configured_slug(): void
    {
        $product = Product::factory()->create(['category_slug' => null]);

        $this->actingAsAdmin()
            ->put(route('admin.products.update', $product), [
                'name' => $product->name,
                'price' => '19.99',
                'category_slug' => 'ultraschallsonden',
                'is_available' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('ultraschallsonden', $product->refresh()->category_slug);
    }

    public function test_unknown_category_slug_is_rejected(): void
    {
        $product = Product::factory()->create(['category_slug' => 'zubehoer']);

        $this->actingAsAdmin()
            ->put(route('admin.products.update', $product), [
                'name' => $product->name,
                'price' => '19.99',
                'category_slug' => 'gibt-es-nicht',
                'is_available' => true,
            ])
            ->assertSessionHasErrors('category_slug');

        $this->assertSame('zubehoer', $product->refresh()->category_slug);
    }

    public function test_technical_specs_can_be_saved_in_order(): void
    {
        $product = Product::factory()->create();

        $this->actingAsAdmin()
            ->put(route('admin.products.update', $product), [
                'name' => $product->name,
                'price' => '19.99',
                'is_available' => true,
                'technical_specs' => [
                    ['label' => ' Gewicht ', 'value' => '2,4 kg '],
                    ['label' => '', 'value' => ''],
                    ['label' => 'Frequenz', 'value' => '2–5 MHz'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame([
            ['label' => 'Gewicht', 'value' => '2,4 kg'],
            ['label' => 'Frequenz', 'value' => '2–5 MHz'],
        ], $product->refresh()->technical_specs);
    }

    public function test_technical_specs_can_be_created_with_product(): void
    {
        $this->actingAsAdmin()
            ->post(route('admin.products.store'), [
                'name' => 'Sonde C5',
                'price' => '199.00',
                'is_available' => true,
                'technical_specs' => [['label' => 'Frequenz', 'value' => '2–5 MHz']],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            [['label' => 'Frequenz', 'value' => '2–5 MHz']],
            Product::query()->where('name', 'Sonde C5')->sole()->technical_specs,
        );
    }

    public function test_technical_specs_can_be_cleared(): void
    {
        $product = Product::factory()->create([
            'technical_specs' => [['label' => 'Gewicht', 'value' => '2,4 kg']],
        ]);

        $this->actingAsAdmin()
            ->put(route('admin.products.update', $product), [
                'name' => $product->name,
                'price' => '19.99',
                'is_available' => true,
                'technical_specs' => [],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame([], $product->refresh()->technical_specs);
    }

    public function test_technical_spec_row_needs_label_and_value(): void
    {
        $product = Product::factory()->create();

        $this->actingAsAdmin()
            ->put(route('admin.products.update', $product), [
                'name' => $product->name,
                'price' => '19.99',
                'is_available' => true,
                'technical_specs' => [
                    ['label' => 'Gewicht', 'value' => ''],
                    ['label' => '', 'value' => '2–5 MHz'],
                ],
            ])
            ->assertSessionHasErrors(['technical_specs.0.value', 'technical_specs.1.label']);

        $this->assertNull($product->refresh()->technical_specs);
    }

    public function test_technical_specs_reject_unexpected_shapes(): void
    {
        $product = Product::factory()->create();

        $this->actingAsAdmin()
            ->put(route('admin.products.update', $product), [
                'name' => $product->name,
                'price' => '19.99',
                'is_available' => true,
                'technical_specs' => [
                    ['label' => 'Gewicht', 'value' => '2,4 kg', 'extra' => 'x'],
                    'kein-array',
                ],
            ])
            ->assertSessionHasErrors(['technical_specs.0', 'technical_specs.1']);
    }

    public function test_edit_page_exposes_technical_specs(): void
    {
        $product = Product::factory()->create([
            'technical_specs' => [['label' => 'Gewicht', 'value' => '2,4 kg']],
        ]);

        $this->actingAsAdmin()
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('product.technical_specs', [['label' => 'Gewicht', 'value' => '2,4 kg']])
            );
    }

    public function test_specialties_are_saved_in_config_order_for_ultrasound_categories(): void
    {
        $product = Product::factory()->create(['category_slug' => 'ultraschallsysteme']);

        $this->actingAsAdmin()
            ->put(route('admin.products.update', $product), [
                'name' => $product->name,
                'price' => '19.99',
                'is_available' => true,
                'category_slug' => 'ultraschallsysteme',
                'specialties' => ['urologie', 'kardiologie'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(['kardiologie', 'urologie'], $product->refresh()->specialties);
    }

    public function test_specialties_can_be_created_with_a_probe(): void
    {
        $this->actingAsAdmin()
            ->post(route('admin.products.store'), [
                'name' => 'Sonde L12',
                'price' => '990.00',
                'is_available' => true,
                'category_slug' => 'ultraschallsonden',
                'specialties' => ['angiologie'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(['angiologie'], Product::query()->where('name', 'Sonde L12')->sole()->specialties);
    }

    public function test_specialties_are_rejected_for_categories_without_specialties(): void
    {
        $product = Product::factory()->create(['category_slug' => 'zubehoer']);

        $this->actingAsAdmin()
            ->put(route('admin.products.update', $product), [
                'name' => $product->name,
                'price' => '19.99',
                'is_available' => true,
                'category_slug' => 'zubehoer',
                'specialties' => ['kardiologie'],
            ])
            ->assertSessionHasErrors('specialties');

        $this->assertNull($product->refresh()->specialties);
    }

    public function test_unknown_or_duplicate_specialties_are_rejected(): void
    {
        $product = Product::factory()->create(['category_slug' => 'ultraschallsysteme']);

        $this->actingAsAdmin()
            ->put(route('admin.products.update', $product), [
                'name' => $product->name,
                'price' => '19.99',
                'is_available' => true,
                'category_slug' => 'ultraschallsysteme',
                'specialties' => ['zahnmedizin', 'kardiologie', 'kardiologie'],
            ])
            ->assertSessionHasErrors(['specialties.0', 'specialties.1', 'specialties.2']);

        $this->assertNull($product->refresh()->specialties);
    }

    public function test_moving_a_product_out_of_an_ultrasound_category_clears_its_specialties(): void
    {
        $product = Product::factory()->create([
            'category_slug' => 'ultraschallsysteme',
            'specialties' => ['kardiologie'],
        ]);

        $this->actingAsAdmin()
            ->put(route('admin.products.update', $product), [
                'name' => $product->name,
                'price' => '19.99',
                'is_available' => true,
                'category_slug' => 'zubehoer',
                'specialties' => [],
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($product->refresh()->specialties);
    }

    public function test_update_without_specialties_field_keeps_existing_specialties(): void
    {
        $product = Product::factory()->create([
            'category_slug' => 'ultraschallsysteme',
            'specialties' => ['kardiologie'],
        ]);

        $this->actingAsAdmin()
            ->put(route('admin.products.update', $product), [
                'name' => 'Neuer Name',
                'price' => '19.99',
                'is_available' => true,
                'category_slug' => 'ultraschallsysteme',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(['kardiologie'], $product->refresh()->specialties);
    }

    public function test_edit_page_offers_specialties_for_ultrasound_categories(): void
    {
        $product = Product::factory()->create([
            'category_slug' => 'ultraschallsonden',
            'specialties' => ['kardiologie'],
        ]);

        $this->actingAsAdmin()
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('product.specialties', ['kardiologie'])
                    ->where('specialtyCategories', ['ultraschallsysteme', 'ultraschallsonden'])
                    ->has('specialties', 10)
                    ->where('specialties.0', ['slug' => 'allgemeinmedizin-innere-medizin', 'name' => 'Allgemeinmedizin / Innere Medizin'])
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
