<?php

namespace Tests\Feature\Support;

use App\Support\ModeMailConfiguration;
use App\Support\PaymentMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OperatingModeTest extends TestCase
{
    use RefreshDatabase;

    private function runAs(string $environment, bool $debug): void
    {
        $this->app['env'] = $environment;
        config(['app.debug' => $debug]);
    }

    /**
     * @return array<string, array{string, bool, string}>
     */
    public static function modes(): array
    {
        return [
            'produktion ohne debug' => ['production', false, 'live'],
            'produktion mit debug' => ['production', true, 'sandbox'],
            'staging ohne debug' => ['staging', false, 'sandbox'],
            'lokal mit debug' => ['local', true, 'sandbox'],
        ];
    }

    #[DataProvider('modes')]
    public function test_live_only_in_production_without_debug(string $environment, bool $debug, string $expected): void
    {
        $this->runAs($environment, $debug);

        $this->assertSame($expected, PaymentMode::current());
    }

    #[DataProvider('modes')]
    public function test_sandbox_banner_follows_the_mode(string $environment, bool $debug, string $expected): void
    {
        $this->runAs($environment, $debug);

        $this->get(route('products.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('sandbox', $expected === 'sandbox')->etc());
    }

    private function configureSmtpModes(): void
    {
        config([
            'mail.default' => 'log',
            'mail.modes' => [
                'sandbox' => [
                    'host' => 'sandbox.smtp.test',
                    'port' => '2525',
                    'username' => 'sandbox@dormed.test',
                    'password' => 'sandbox-pw',
                    'from_address' => null,
                ],
                'live' => [
                    'host' => 'live.smtp.test',
                    'port' => '587',
                    'username' => 'smtp-user',
                    'password' => 'live-pw',
                    'from_address' => 'shop@dormed.test',
                ],
            ],
        ]);
    }

    public function test_sandbox_smtp_credentials_are_used_outside_live(): void
    {
        $this->runAs('production', true);
        $this->configureSmtpModes();

        ModeMailConfiguration::apply();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('sandbox.smtp.test', config('mail.mailers.smtp.host'));
        $this->assertSame(2525, config('mail.mailers.smtp.port'));
        $this->assertSame('sandbox-pw', config('mail.mailers.smtp.password'));
        $this->assertSame('sandbox@dormed.test', config('mail.from.address'));
    }

    public function test_live_smtp_credentials_are_used_in_live(): void
    {
        $this->runAs('production', false);
        $this->configureSmtpModes();

        ModeMailConfiguration::apply();

        $this->assertSame('live.smtp.test', config('mail.mailers.smtp.host'));
        $this->assertSame('live-pw', config('mail.mailers.smtp.password'));
        $this->assertSame('shop@dormed.test', config('mail.from.address'));
    }

    public function test_mailer_stays_unchanged_without_host_for_active_mode(): void
    {
        $this->runAs('local', true);
        config(['mail.default' => 'log', 'mail.modes.sandbox.host' => null]);

        ModeMailConfiguration::apply();

        $this->assertSame('log', config('mail.default'));
    }

    public function test_smtp_credentials_in_the_database_are_ignored(): void
    {
        $this->runAs('local', true);
        config(['mail.default' => 'log', 'mail.modes.sandbox.host' => null]);
        DB::table('settings')->insert([
            ['key' => 'mail.smtp_host', 'value' => 'db.smtp.test', 'created_at' => now(), 'updated_at' => now()],
        ]);

        ModeMailConfiguration::apply();

        $this->assertSame('log', config('mail.default'));
        $this->assertNotSame('db.smtp.test', config('mail.mailers.smtp.host'));
    }

    public function test_legacy_credentials_command_prints_env_lines_from_old_settings(): void
    {
        DB::table('settings')->insert([
            ['key' => 'paypal.live.client_id', 'value' => 'live-client', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'paypal.live.client_secret', 'value' => encrypt('live-secret'), 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'mail.smtp_host', 'value' => 'smtp.example.test', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->artisan('settings:legacy-credentials')
            ->expectsOutputToContain("PAYPAL_LIVE_CLIENT_ID='live-client'")
            ->expectsOutputToContain("PAYPAL_LIVE_CLIENT_SECRET='live-secret'")
            ->expectsOutputToContain("MAIL_LIVE_HOST='smtp.example.test'")
            ->assertSuccessful();
    }
}
