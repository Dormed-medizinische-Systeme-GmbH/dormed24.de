<?php

namespace Tests\Feature\Shop;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Smoke-Tests für Seiten ohne eigene Logik: Informations- und
 * Unternehmensseiten, Sitemap und Passkey-Endpunkte.
 */
class StaticPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function staticPages(): array
    {
        return [
            'Versand' => ['versand', 'Informationen/VersandUndZahlung'],
            'Zahlung' => ['zahlung', 'Informationen/Zahlung'],
            'FAQ' => ['faq', 'Informationen/FAQ'],
            'Widerrufsbelehrung' => ['widerrufsbelehrung', 'Informationen/Widerrufsbelehrung'],
            'Impressum' => ['impressum', 'Unternehmen/Impressum'],
            'AGB' => ['agb', 'Unternehmen/AGB'],
            'Datenschutz' => ['datenschutz', 'Unternehmen/Datenschutz'],
        ];
    }

    #[DataProvider('staticPages')]
    public function test_static_page_renders(string $routeName, string $component): void
    {
        $this->get(route($routeName))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component($component));
    }

    public function test_sitemap_lists_static_pages_categories_and_products(): void
    {
        $product = Product::factory()->create();

        $response = $this->get(route('sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml');

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml, 'Sitemap ist kein gültiges XML.');

        $locations = collect(iterator_to_array($xml->url, false))->map(fn ($url) => (string) $url->loc);
        $this->assertContains(url('/'), $locations);
        $this->assertContains(route('impressum'), $locations);
        $this->assertContains(route('products.show', $product), $locations);
    }

    public function test_every_sitemap_url_is_reachable(): void
    {
        Product::factory()->create();

        $xml = simplexml_load_string($this->get(route('sitemap'))->getContent());

        foreach ($xml->url as $url) {
            $path = parse_url((string) $url->loc, PHP_URL_PATH) ?: '/';
            $this->get($path)->assertOk();
        }
    }

    public function test_passkey_well_known_points_to_security_settings(): void
    {
        $this->getJson('/.well-known/passkey-endpoints')
            ->assertOk()
            ->assertExactJson([
                'enroll' => route('security.edit'),
                'manage' => route('security.edit'),
            ]);
    }
}
