<?php

namespace Tests\Feature\Admin;

use App\Models\Manufacturer;
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
}
