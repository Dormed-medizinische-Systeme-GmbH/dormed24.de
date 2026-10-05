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

    public function test_hash_password_command_prints_env_line_with_bcrypt_hash(): void
    {
        $this->artisan('admin:hash-password')
            ->expectsQuestion('Neues Admin-Passwort (mind. 12 Zeichen)', 'ein-sicheres-passwort')
            ->expectsQuestion('Passwort wiederholen', 'ein-sicheres-passwort')
            ->expectsOutputToContain("ADMIN_PASSWORD='$2y$")
            ->assertSuccessful();
    }

    public function test_hash_password_command_rejects_short_and_mismatching_passwords(): void
    {
        $this->artisan('admin:hash-password')
            ->expectsQuestion('Neues Admin-Passwort (mind. 12 Zeichen)', 'zu-kurz')
            ->expectsOutputToContain('mindestens 12 Zeichen')
            ->expectsQuestion('Neues Admin-Passwort (mind. 12 Zeichen)', 'ein-sicheres-passwort')
            ->expectsQuestion('Passwort wiederholen', 'ein-anderes-passwort')
            ->expectsOutputToContain('stimmen nicht überein')
            ->expectsQuestion('Neues Admin-Passwort (mind. 12 Zeichen)', 'ein-sicheres-passwort')
            ->expectsQuestion('Passwort wiederholen', 'ein-sicheres-passwort')
            ->assertSuccessful();
    }
}
