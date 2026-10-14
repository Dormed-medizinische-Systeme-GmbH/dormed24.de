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
            'articles index' => ['admin.articles.index'],
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

    /**
     * Kategorien (config/categories.php), PayPal-/SMTP-Zugangsdaten (Env) sowie
     * Kontakt und Bankverbindung (config/shop.php) und Versandarten
     * (config/shipping.php) werden nicht mehr im Admin
     * gepflegt — die Seiten dafür gibt es nicht mehr.
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
            'allgemein' => ['/admin/settings/general'],
            'einstellungen' => ['/admin/settings'],
            'versandarten' => ['/admin/settings/shipping'],
        ];
    }

    #[DataProvider('removedAdminPages')]
    public function test_removed_admin_pages_no_longer_exist(string $path): void
    {
        $this->actingAsAdmin()->get($path)->assertNotFound();
    }

    /**
     * Versandarten stehen in config/shipping.php
     * (.docs/adr/0006-admin-nur-inhaltspflege.md).
     */
    public function test_shipping_methods_can_no_longer_be_changed_in_admin(): void
    {
        $this->actingAsAdmin()
            ->post('/admin/settings/shipping', ['name' => 'Gratis', 'price' => '0'])
            ->assertNotFound();

        $this->actingAsAdmin()
            ->put('/admin/settings/shipping/1', ['name' => 'Gratis', 'price' => '0'])
            ->assertNotFound();

        $this->actingAsAdmin()
            ->delete('/admin/settings/shipping/1')
            ->assertNotFound();
    }

    public function test_general_settings_can_no_longer_be_saved(): void
    {
        $this->actingAsAdmin()
            ->put('/admin/settings', ['settings' => ['shop.email' => 'x@example.com']])
            ->assertStatus(405);
    }

    public function test_customer_session_does_not_grant_admin_access(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
    }
}
