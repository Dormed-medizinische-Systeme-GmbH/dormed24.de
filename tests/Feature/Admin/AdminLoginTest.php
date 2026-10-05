<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_form_renders(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Login'));
    }

    public function test_admin_can_log_in_with_env_credentials(): void
    {
        $this->configureEnvAdmin();

        $this->post(route('admin.login.store'), [
            'email' => self::ADMIN_EMAIL,
            'password' => self::ADMIN_PASSWORD,
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertTrue(Auth::guard('admin')->check());
        $this->assertSame(self::ADMIN_EMAIL, Auth::guard('admin')->id());
    }

    public function test_email_is_matched_case_insensitively(): void
    {
        $this->configureEnvAdmin('Admin@Dormed.test');

        $this->post(route('admin.login.store'), [
            'email' => 'admin@DORMED.test',
            'password' => self::ADMIN_PASSWORD,
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertTrue(Auth::guard('admin')->check());
    }

    public function test_admin_session_survives_the_next_request(): void
    {
        $this->configureEnvAdmin();

        $this->post(route('admin.login.store'), [
            'email' => self::ADMIN_EMAIL,
            'password' => self::ADMIN_PASSWORD,
        ]);

        // Neuer Guard-Zustand: der Admin muss allein aus der Session-ID
        // (retrieveById) wiederhergestellt werden.
        Auth::forgetGuards();

        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->configureEnvAdmin();

        $this->post(route('admin.login.store'), [
            'email' => self::ADMIN_EMAIL,
            'password' => 'falsch',
        ])->assertSessionHasErrors('email');

        $this->assertFalse(Auth::guard('admin')->check());
    }

    public function test_login_fails_with_unknown_email(): void
    {
        $this->configureEnvAdmin();

        $this->post(route('admin.login.store'), [
            'email' => 'jemand@dormed.test',
            'password' => self::ADMIN_PASSWORD,
        ])->assertSessionHasErrors('email');

        $this->assertFalse(Auth::guard('admin')->check());
    }

    public function test_login_is_impossible_without_admin_env(): void
    {
        config(['auth.admin' => ['email' => null, 'password' => null]]);

        $this->post(route('admin.login.store'), [
            'email' => self::ADMIN_EMAIL,
            'password' => self::ADMIN_PASSWORD,
        ])->assertSessionHasErrors('email');

        $this->assertFalse(Auth::guard('admin')->check());
    }

    public function test_plaintext_admin_password_in_env_is_rejected(): void
    {
        config(['auth.admin' => [
            'email' => self::ADMIN_EMAIL,
            'password' => self::ADMIN_PASSWORD,
        ]]);

        $this->post(route('admin.login.store'), [
            'email' => self::ADMIN_EMAIL,
            'password' => self::ADMIN_PASSWORD,
        ])->assertSessionHasErrors('email');

        $this->assertFalse(Auth::guard('admin')->check());
    }

    public function test_session_is_dropped_when_admin_email_changes(): void
    {
        $this->configureEnvAdmin();

        $this->post(route('admin.login.store'), [
            'email' => self::ADMIN_EMAIL,
            'password' => self::ADMIN_PASSWORD,
        ]);

        $this->configureEnvAdmin('neuer-admin@dormed.test');
        Auth::forgetGuards();

        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->post(route('admin.login.store'), [])
            ->assertSessionHasErrors(['email', 'password']);
    }

    public function test_shared_admin_prop_never_contains_the_password_hash(): void
    {
        $this->actingAsAdmin()
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page->where('auth.admin', [
                    'name' => 'Admin',
                    'email' => self::ADMIN_EMAIL,
                ])->etc()
            );
    }

    public function test_admin_can_log_out(): void
    {
        $this->actingAsAdmin()
            ->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'));

        $this->assertFalse(Auth::guard('admin')->check());
    }

    public function test_admin_login_does_not_need_the_users_table(): void
    {
        Schema::dropIfExists('users');
        $this->configureEnvAdmin();

        $this->post(route('admin.login.store'), [
            'email' => self::ADMIN_EMAIL,
            'password' => self::ADMIN_PASSWORD,
        ])->assertRedirect(route('admin.dashboard'));
    }
}
