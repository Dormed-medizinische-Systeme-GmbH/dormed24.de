<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_update_rejects_invalid_smtp_port(): void
    {
        $this->asAdmin()
            ->put(route('admin.settings.update'), [
                'settings' => ['mail.smtp_port' => '99999'],
            ])
            ->assertSessionHasErrors('settings.mail.smtp_port');

        $this->assertNull(Setting::get('mail.smtp_port'));
    }

    public function test_update_accepts_valid_shop_email_and_port(): void
    {
        $this->asAdmin()
            ->put(route('admin.settings.update'), [
                'settings' => ['shop.email' => 'shop@dormed24.de', 'mail.smtp_port' => '587'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('shop@dormed24.de', Setting::get('shop.email'));
        $this->assertSame('587', Setting::get('mail.smtp_port'));
    }
}
