<?php

namespace Tests\Feature\Admin;

use App\Models\ShippingMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShippingMethodControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_admin_login(): void
    {
        $method = ShippingMethod::factory()->create();

        $this->get(route('admin.settings.shipping'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.settings.shipping.store'), ['name' => 'Neu'])->assertRedirect(route('admin.login'));
        $this->put(route('admin.settings.shipping.update', $method), ['name' => 'Geändert'])->assertRedirect(route('admin.login'));
        $this->delete(route('admin.settings.shipping.destroy', $method))->assertRedirect(route('admin.login'));

        $this->assertDatabaseHas('shipping_methods', ['id' => $method->id, 'name' => $method->name]);
        $this->assertSame(1, ShippingMethod::count());
    }

    public function test_index_lists_methods_in_sort_order(): void
    {
        ShippingMethod::factory()->create(['name' => 'Spedition', 'sort_order' => 3]);
        ShippingMethod::factory()->create(['name' => 'Standard', 'sort_order' => 1]);
        ShippingMethod::factory()->create(['name' => 'Express', 'sort_order' => 2]);

        $this->actingAsAdmin()
            ->get(route('admin.settings.shipping'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Settings/Shipping')
                ->has('methods', 3)
                ->where('methods.0.name', 'Standard')
                ->where('methods.1.name', 'Express')
                ->where('methods.2.name', 'Spedition'));
    }

    public function test_store_creates_a_method_at_the_end_of_the_list(): void
    {
        ShippingMethod::factory()->create(['sort_order' => 5]);

        $this->actingAsAdmin()
            ->post(route('admin.settings.shipping.store'), [
                'name' => 'Express',
                'description' => 'Zustellung am nächsten Werktag',
                'price' => '12.50',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('shipping_methods', [
            'name' => 'Express',
            'description' => 'Zustellung am nächsten Werktag',
            'price' => '12.50',
            'sort_order' => 6,
        ]);
    }

    public function test_first_method_gets_sort_order_one(): void
    {
        $this->actingAsAdmin()
            ->post(route('admin.settings.shipping.store'), ['name' => 'Standard', 'price' => '4.90'])
            ->assertRedirect();

        $this->assertDatabaseHas('shipping_methods', ['name' => 'Standard', 'sort_order' => 1]);
    }

    public function test_price_is_optional_and_means_on_request(): void
    {
        $this->actingAsAdmin()
            ->post(route('admin.settings.shipping.store'), ['name' => 'Spedition'])
            ->assertSessionHasNoErrors();

        $this->assertNull(ShippingMethod::query()->where('name', 'Spedition')->sole()->price);
    }

    public function test_a_price_of_zero_is_accepted(): void
    {
        $this->actingAsAdmin()
            ->post(route('admin.settings.shipping.store'), ['name' => 'Abholung', 'price' => '0'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('shipping_methods', ['name' => 'Abholung', 'price' => '0.00']);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidInput(): array
    {
        return [
            'name missing' => [['price' => '4.90'], 'name'],
            'name empty' => [['name' => '', 'price' => '4.90'], 'name'],
            'name too long' => [['name' => str_repeat('a', 256), 'price' => '4.90'], 'name'],
            'description too long' => [['name' => 'Standard', 'description' => str_repeat('a', 501)], 'description'],
            'price negative' => [['name' => 'Standard', 'price' => '-1'], 'price'],
            'price not numeric' => [['name' => 'Standard', 'price' => 'gratis'], 'price'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    #[DataProvider('invalidInput')]
    public function test_store_rejects_invalid_input(array $input, string $invalidField): void
    {
        $this->actingAsAdmin()
            ->post(route('admin.settings.shipping.store'), $input)
            ->assertSessionHasErrors($invalidField);

        $this->assertSame(0, ShippingMethod::count());
    }

    public function test_update_changes_the_method(): void
    {
        $method = ShippingMethod::factory()->create([
            'name' => 'Standard',
            'description' => 'alt',
            'price' => '4.90',
            'sort_order' => 2,
        ]);

        $this->actingAsAdmin()
            ->put(route('admin.settings.shipping.update', $method), [
                'name' => 'Standard Plus',
                'description' => 'neu',
                'price' => '5.90',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('shipping_methods', [
            'id' => $method->id,
            'name' => 'Standard Plus',
            'description' => 'neu',
            'price' => '5.90',
            'sort_order' => 2,
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    #[DataProvider('invalidInput')]
    public function test_update_rejects_invalid_input(array $input, string $invalidField): void
    {
        $method = ShippingMethod::factory()->create(['name' => 'Standard', 'price' => '4.90']);

        $this->actingAsAdmin()
            ->put(route('admin.settings.shipping.update', $method), $input)
            ->assertSessionHasErrors($invalidField);

        $this->assertDatabaseHas('shipping_methods', ['id' => $method->id, 'name' => 'Standard', 'price' => '4.90']);
    }

    public function test_update_of_unknown_method_returns_not_found(): void
    {
        $this->actingAsAdmin()
            ->put(route('admin.settings.shipping.update', 9999), ['name' => 'Geist'])
            ->assertNotFound();
    }

    public function test_destroy_removes_the_method(): void
    {
        $method = ShippingMethod::factory()->create();
        $other = ShippingMethod::factory()->create();

        $this->actingAsAdmin()
            ->delete(route('admin.settings.shipping.destroy', $method))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('shipping_methods', ['id' => $method->id]);
        $this->assertDatabaseHas('shipping_methods', ['id' => $other->id]);
    }
}
