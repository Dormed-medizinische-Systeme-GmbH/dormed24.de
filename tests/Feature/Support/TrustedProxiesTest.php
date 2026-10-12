<?php

namespace Tests\Feature\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Hinter dem Nginx auf dem Host (Docker-Stack) kommen Schema und Client-IP
 * nur über X-Forwarded-*. Laravel übernimmt sie ausschließlich, wenn
 * TRUSTED_PROXIES gesetzt ist; ohne Proxy davor bleiben sie wirkungslos.
 */
class TrustedProxiesTest extends TestCase
{
    private const FORWARDED_HEADERS = [
        'X-Forwarded-For' => '203.0.113.7',
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'dev.dormed24.de',
        'X-Forwarded-Port' => '443',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/__trusted-proxies', fn (Request $request) => [
            'secure' => $request->secure(),
            'ip' => $request->ip(),
            'url' => url('/warenkorb'),
        ]);
    }

    public function test_forwarded_headers_are_ignored_without_trusted_proxies(): void
    {
        config(['trustedproxy.proxies' => null]);

        $this->get('/__trusted-proxies', self::FORWARDED_HEADERS)
            ->assertOk()
            ->assertJson([
                'secure' => false,
                'ip' => '127.0.0.1',
                'url' => 'http://localhost/warenkorb',
            ]);
    }

    public function test_forwarded_headers_apply_when_every_proxy_is_trusted(): void
    {
        config(['trustedproxy.proxies' => '*']);

        $this->get('/__trusted-proxies', self::FORWARDED_HEADERS)
            ->assertOk()
            ->assertJson([
                'secure' => true,
                'ip' => '203.0.113.7',
                'url' => 'https://dev.dormed24.de/warenkorb',
            ]);
    }

    public function test_only_listed_proxies_are_trusted(): void
    {
        config(['trustedproxy.proxies' => '10.0.0.1, 10.0.0.2']);

        $this->get('/__trusted-proxies', self::FORWARDED_HEADERS)
            ->assertOk()
            ->assertJson(['secure' => false, 'ip' => '127.0.0.1']);
    }
}
