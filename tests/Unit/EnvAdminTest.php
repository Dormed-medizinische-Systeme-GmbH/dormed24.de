<?php

namespace Tests\Unit;

use App\Auth\EnvAdmin;
use PHPUnit\Framework\TestCase;

class EnvAdminTest extends TestCase
{
    private function admin(): EnvAdmin
    {
        return new EnvAdmin(EnvAdmin::NAME, 'Admin@Dormed.example', '$2y$04$geheimerhashgeheimerhashgeheimerhashgeheimerhashge');
    }

    public function test_serialized_admin_never_contains_the_password_hash(): void
    {
        $admin = $this->admin();

        $this->assertSame(['name' => 'Shop-Admin', 'email' => 'Admin@Dormed.example'], $admin->toArray());
        $this->assertSame('{"name":"Shop-Admin","email":"Admin@Dormed.example"}', json_encode($admin));
        $this->assertStringNotContainsString('$2y$', (string) json_encode($admin));
    }

    public function test_identifier_is_the_lowercased_email(): void
    {
        $admin = $this->admin();

        $this->assertSame('email', $admin->getAuthIdentifierName());
        $this->assertSame('admin@dormed.example', $admin->getAuthIdentifier());
        $this->assertSame('password', $admin->getAuthPasswordName());
        $this->assertStringStartsWith('$2y$', $admin->getAuthPassword());
    }

    public function test_remember_me_is_not_supported(): void
    {
        $admin = $this->admin();
        $admin->setRememberToken('token');

        $this->assertNull($admin->getRememberToken());
        $this->assertSame('', $admin->getRememberTokenName());
    }
}
