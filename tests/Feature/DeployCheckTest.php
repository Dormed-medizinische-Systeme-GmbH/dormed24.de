<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Temporärer Test zur Deploy-Validierungs-Route (/deploy-check).
 * Wird zusammen mit der Route wieder entfernt, sobald der erste
 * erfolgreiche Deploy über die Route verifiziert wurde.
 */
class DeployCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_deploy_check_returns_ok(): void
    {
        $this->get('/deploy-check')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('OK');
    }
}
