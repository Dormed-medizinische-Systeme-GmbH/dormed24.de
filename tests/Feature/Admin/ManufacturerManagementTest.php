<?php

namespace Tests\Feature\Admin;

use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManufacturerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_manufacturers(): void
    {
        Manufacturer::factory()->count(3)->create();

        $this->actingAsAdmin()
            ->get(route('admin.manufacturers.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Manufacturers/Index')->has('manufacturers'));
    }

    public function test_manufacturer_can_be_updated(): void
    {
        $manufacturer = Manufacturer::factory()->create();

        $this->actingAsAdmin()
            ->put(route('admin.manufacturers.update', $manufacturer), [
                'name' => 'Dräger',
                'country' => 'Deutschland',
                'contact_email' => 'info@draeger.test',
            ])
            ->assertRedirect(route('admin.manufacturers.index'));

        $this->assertDatabaseHas('manufacturers', [
            'id' => $manufacturer->id,
            'name' => 'Dräger',
            'contact_email' => 'info@draeger.test',
        ]);
    }

    public function test_update_requires_name(): void
    {
        $manufacturer = Manufacturer::factory()->create();

        $this->actingAsAdmin()
            ->put(route('admin.manufacturers.update', $manufacturer), ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_update_rejects_invalid_email(): void
    {
        $manufacturer = Manufacturer::factory()->create();

        $this->actingAsAdmin()
            ->put(route('admin.manufacturers.update', $manufacturer), [
                'name' => 'Dräger',
                'contact_email' => 'keine-email',
            ])
            ->assertSessionHasErrors('contact_email');
    }

    public function test_manufacturer_can_be_deleted(): void
    {
        $manufacturer = Manufacturer::factory()->create();

        $this->actingAsAdmin()
            ->delete(route('admin.manufacturers.destroy', $manufacturer))
            ->assertRedirect(route('admin.manufacturers.index'));

        $this->assertDatabaseMissing('manufacturers', ['id' => $manufacturer->id]);
    }

    public function test_manufacturer_can_be_created(): void
    {
        $this->actingAsAdmin()
            ->get(route('admin.manufacturers.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Manufacturers/Create'));

        $response = $this->actingAsAdmin()->post(route('admin.manufacturers.store'), [
            'name' => 'Mindray',
            'country' => 'China',
            'contact_email' => 'info@mindray.test',
        ]);

        $manufacturer = Manufacturer::query()->where('name', 'Mindray')->sole();
        $response->assertRedirect(route('admin.manufacturers.edit', $manufacturer))
            ->assertSessionHas('success', 'Hersteller erstellt.');
    }

    public function test_store_requires_name(): void
    {
        $this->actingAsAdmin()
            ->post(route('admin.manufacturers.store'), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('manufacturers', 0);
    }

    public function test_edit_page_shows_the_manufacturer(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Siemens Healthineers']);

        $this->actingAsAdmin()
            ->get(route('admin.manufacturers.edit', $manufacturer))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Manufacturers/Edit')
                ->where('manufacturer.name', 'Siemens Healthineers'));
    }

    public function test_manufacturer_with_products_cannot_be_deleted(): void
    {
        $manufacturer = Manufacturer::factory()->create();
        Product::factory()->for($manufacturer)->create();

        $this->actingAsAdmin()
            ->from(route('admin.manufacturers.index'))
            ->delete(route('admin.manufacturers.destroy', $manufacturer))
            ->assertRedirect(route('admin.manufacturers.index'))
            ->assertSessionHas('error');

        $this->assertModelExists($manufacturer);
    }
}
