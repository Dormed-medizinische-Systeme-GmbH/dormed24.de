<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SettingControllerTest extends TestCase
{
    use RefreshDatabase;

    private function asAdmin(): static
    {
        return $this->actingAsAdmin();
    }

    public function test_update_stores_whitelisted_settings(): void
    {
        $this->asAdmin()
            ->put(route('admin.settings.update'), [
                'settings' => ['shop.name' => 'dormed 24'],
            ])
            ->assertRedirect();

        $this->assertSame('dormed 24', Setting::get('shop.name'));
    }

    public function test_update_rejects_unknown_settings_key(): void
    {
        $this->asAdmin()
            ->put(route('admin.settings.update'), [
                'settings' => ['not.a.real.key' => 'hacked'],
            ])
            ->assertSessionHasErrors('settings');

        $this->assertNull(Setting::get('not.a.real.key'));
    }

    public function test_update_rejects_invalid_shop_email(): void
    {
        $this->asAdmin()
            ->put(route('admin.settings.update'), [
                'settings' => ['shop.email' => 'kein-at-zeichen'],
            ])
            ->assertSessionHasErrors('settings.shop.email');

        $this->assertNull(Setting::get('shop.email'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function credentialKeysNowInEnv(): array
    {
        return [
            'smtp host' => ['mail.smtp_host'],
            'smtp passwort' => ['mail.smtp_password'],
            'paypal client id' => ['paypal.live.client_id'],
            'paypal secret' => ['paypal.sandbox.client_secret'],
            'paypal webhook' => ['paypal.webhook_id'],
        ];
    }

    #[DataProvider('credentialKeysNowInEnv')]
    public function test_credentials_can_no_longer_be_stored_in_the_database(string $key): void
    {
        $this->asAdmin()
            ->put(route('admin.settings.update'), [
                'settings' => [$key => 'geheim'],
            ])
            ->assertSessionHasErrors('settings');

        $this->assertNull(Setting::get($key));
    }

    public function test_update_accepts_valid_shop_email(): void
    {
        $this->asAdmin()
            ->put(route('admin.settings.update'), [
                'settings' => ['shop.email' => 'shop@dormed24.de'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('shop@dormed24.de', Setting::get('shop.email'));
    }
}
