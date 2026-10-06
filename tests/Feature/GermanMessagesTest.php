<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Die App ist rein deutsch: Meldungen aus Laravel, Fortify und den eigenen
 * Controllern dürfen nicht auf Englisch durchrutschen.
 */
class GermanMessagesTest extends TestCase
{
    use RefreshDatabase;

    private const FAILED_LOGIN = 'Diese Zugangsdaten stimmen nicht mit unseren Daten überein.';

    public function test_failed_admin_login_is_reported_in_german(): void
    {
        $this->configureEnvAdmin();

        $this->post(route('admin.login.store'), [
            'email' => self::ADMIN_EMAIL,
            'password' => 'falsch',
        ])->assertSessionHasErrors(['email' => self::FAILED_LOGIN]);
    }

    public function test_failed_customer_login_is_reported_in_german(): void
    {
        $customer = Customer::factory()->create();

        $this->post(route('login.store'), [
            'email' => $customer->email,
            'password' => 'falsch',
        ])->assertSessionHasErrors(['email' => self::FAILED_LOGIN]);
    }

    public function test_profile_update_toast_is_german(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer)
            ->patch(route('profile.update'), [
                'name' => 'Praxis Müller',
                'email' => $customer->email,
            ])
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Profil aktualisiert.']);
    }

    public function test_password_update_toast_is_german(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer)
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'neues-passwort',
                'password_confirmation' => 'neues-passwort',
            ])
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Passwort aktualisiert.']);
    }

    public function test_password_reset_link_status_is_german(): void
    {
        Notification::fake();
        $customer = Customer::factory()->create();

        $this->post(route('password.email'), ['email' => $customer->email])
            ->assertSessionHas('status', 'Wir haben Ihnen einen Link zum Zurücksetzen des Passworts per E-Mail geschickt.');
    }

    public function test_invalid_password_reset_token_is_reported_in_german(): void
    {
        $customer = Customer::factory()->create();

        $this->post(route('password.update'), [
            'token' => 'ungueltig',
            'email' => $customer->email,
            'password' => 'neues-passwort',
            'password_confirmation' => 'neues-passwort',
        ])->assertSessionHasErrors(['email' => 'Dieser Link zum Zurücksetzen des Passworts ist ungültig.']);
    }

    public function test_fortify_two_factor_messages_are_german(): void
    {
        $this->assertSame(
            'Der eingegebene Zwei-Faktor-Code ist ungültig.',
            __('The provided two factor authentication code was invalid.'),
        );
        $this->assertSame(
            'Der eingegebene Wiederherstellungscode ist ungültig.',
            __('The provided two factor recovery code was invalid.'),
        );
    }
}
