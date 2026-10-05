<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Log;

/**
 * User-Provider für den `admin`-Guard: genau ein Admin aus der Konfiguration
 * (config('auth.admin'), gespeist aus ADMIN_EMAIL / ADMIN_PASSWORD). ADMIN_PASSWORD muss ein bcrypt-Hash sein, erzeugt mit
 * `php artisan admin:hash-password`. Fehlt etwas, ist kein Admin-Login möglich.
 */
final class EnvAdminUserProvider implements UserProvider
{
    /**
     * @param  array{email?: string|null, password?: string|null}  $config
     */
    public function __construct(
        private readonly Hasher $hasher,
        private readonly array $config,
    ) {}

    public function retrieveById($identifier): ?Authenticatable
    {
        $admin = $this->admin();

        return $admin !== null && is_string($identifier) && $identifier === $admin->getAuthIdentifier()
            ? $admin
            : null;
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, $token): void
    {
        //
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $admin = $this->admin();
        $email = $credentials['email'] ?? null;

        if ($admin === null || ! is_string($email)) {
            return null;
        }

        return mb_strtolower(trim($email)) === $admin->getAuthIdentifier() ? $admin : null;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $password = $credentials['password'] ?? null;

        if (! is_string($password) || $password === '') {
            return false;
        }

        return $this->hasher->check($password, $user->getAuthPassword());
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        // Der Hash liegt in der Env und kann von hier aus nicht aktualisiert werden.
    }

    private function admin(): ?EnvAdmin
    {
        $email = trim((string) ($this->config['email'] ?? ''));
        $passwordHash = (string) ($this->config['password'] ?? '');

        if ($email === '' || $passwordHash === '') {
            return null;
        }

        // Klartext-Passwörter in der Env werden bewusst nicht akzeptiert.
        if (password_get_info($passwordHash)['algo'] === null) {
            Log::warning('ADMIN_PASSWORD ist kein Passwort-Hash. Admin-Login ist deaktiviert, bis ein Hash per `php artisan admin:hash-password` gesetzt ist.');

            return null;
        }

        return new EnvAdmin(
            name: 'Admin',
            email: $email,
            passwordHash: $passwordHash,
        );
    }
}
