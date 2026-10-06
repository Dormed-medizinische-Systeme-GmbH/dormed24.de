<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Der eine Shop-Admin. Kommt aus der Env (ADMIN_EMAIL / ADMIN_PASSWORD),
 * nicht aus der Datenbank. Der Name ist fest self::NAME (nur Anzeige im Panel). Identifier ist die
 * (kleingeschriebene) E-Mail: Wird sie geändert, sind alte Sessions ungültig.
 *
 * @implements Arrayable<string, string>
 */
final class EnvAdmin implements Arrayable, Authenticatable, JsonSerializable
{
    /** Anzeigename im Admin-Panel. */
    public const NAME = 'Shop-Admin';

    public function __construct(
        public readonly string $name,
        public readonly string $email,
        private readonly string $passwordHash,
    ) {}

    public function getAuthIdentifierName(): string
    {
        return 'email';
    }

    public function getAuthIdentifier(): string
    {
        return mb_strtolower($this->email);
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return $this->passwordHash;
    }

    /**
     * "Angemeldet bleiben" gibt es für den Admin nicht (kein Speicherort für
     * das Remember-Token ohne Datenbank).
     */
    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        //
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    /**
     * Wird als `auth.admin` ans Frontend geteilt — niemals den Hash mitgeben.
     *
     * @return array{name: string, email: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
        ];
    }

    /**
     * @return array{name: string, email: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
