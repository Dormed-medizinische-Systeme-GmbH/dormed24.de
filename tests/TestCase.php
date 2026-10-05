<?php

namespace Tests;

use App\Auth\EnvAdmin;
use App\Models\Setting;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected const ADMIN_EMAIL = 'admin@dormed.test';

    protected const ADMIN_PASSWORD = 'admin-passwort-1234';

    protected function setUp(): void
    {
        parent::setUp();

        // Setting::get()/set() memoize per PHP process to save queries within
        // a single real request; the test suite runs many simulated requests
        // in one process, so the memo must be reset between tests.
        Setting::flushMemo();
    }

    /**
     * Setzt den Env-Admin (config auth.admin) für den Test, wie ihn
     * ADMIN_EMAIL / ADMIN_PASSWORD in Produktion liefern.
     */
    protected function configureEnvAdmin(
        string $email = self::ADMIN_EMAIL,
        string $password = self::ADMIN_PASSWORD,
    ): EnvAdmin {
        $passwordHash = Hash::make($password);

        config(['auth.admin' => [
            'name' => 'Test Admin',
            'email' => $email,
            'password' => $passwordHash,
        ]]);

        return new EnvAdmin('Test Admin', $email, $passwordHash);
    }

    protected function actingAsAdmin(): static
    {
        return $this->actingAs($this->configureEnvAdmin(), 'admin');
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
