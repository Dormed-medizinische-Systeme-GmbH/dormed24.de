<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function guardedRoutes(): array
    {
        return [
            'dashboard' => ['admin.dashboard'],
            'products index' => ['admin.products.index'],
            'orders index' => ['admin.orders.index'],
            'customers index' => ['admin.customers.index'],
            'manufacturers index' => ['admin.manufacturers.index'],
            'social posts index' => ['admin.social-posts.index'],
            'settings general' => ['admin.settings.general'],
        ];
    }

    #[DataProvider('guardedRoutes')]
    public function test_guests_are_redirected_to_admin_login(string $routeName): void
    {
        $this->get(route($routeName))->assertRedirect(route('admin.login'));
    }

    #[DataProvider('guardedRoutes')]
    public function test_authenticated_admin_can_access(string $routeName): void
    {
        $this->actingAsAdmin()
            ->get(route($routeName))
            ->assertOk();
    }

    public function test_settings_index_redirects_to_general(): void
    {
        $this->actingAsAdmin()
            ->get(route('admin.settings.index'))
            ->assertRedirect('/admin/settings/general');
    }

    /**
     * Kategorien (config/categories.php) sowie PayPal-/SMTP-Zugangsdaten (Env)
     * werden nicht mehr im Admin gepflegt — die Seiten dafür gibt es nicht mehr.
     *
     * @return array<string, array{string}>
     */
    public static function removedAdminPages(): array
    {
        return [
            'kategorien' => ['/admin/categories'],
            'kategorie anlegen' => ['/admin/categories/create'],
            'mailversand' => ['/admin/settings/mail'],
            'zahlungsarten' => ['/admin/settings/payment'],
        ];
    }

    #[DataProvider('removedAdminPages')]
    public function test_removed_admin_pages_no_longer_exist(string $path): void
    {
        $this->actingAsAdmin()->get($path)->assertNotFound();
    }

    public function test_customer_session_does_not_grant_admin_access(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
    }
}
