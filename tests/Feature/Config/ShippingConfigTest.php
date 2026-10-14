<?php

namespace Tests\Feature\Config;

use App\Support\Cart\ShippingMethods;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use UnexpectedValueException;

/**
 * Deploy-Gate für config/shipping.php: Die echte Datei muss gültig sein,
 * sonst schlägt die CI fehl und es wird nicht deployt. Die übrigen Tests
 * stellen sicher, dass die Prüfung jeden Fehlertyp auch wirklich erkennt.
 */
class ShippingConfigTest extends TestCase
{
    public function test_the_shipping_config_file_is_valid(): void
    {
        $entries = require config_path('shipping.php');

        $this->assertSame([], ShippingMethods::errors($entries), 'config/shipping.php ist ungültig.');
    }

    public function test_the_shipping_config_file_is_what_the_app_reads(): void
    {
        $this->assertSame(require config_path('shipping.php'), config('shipping'));
    }

    public function test_every_configured_method_is_offered_in_configured_order(): void
    {
        $entries = require config_path('shipping.php');
        $methods = ShippingMethods::all();

        $this->assertSame(array_column($entries, 'key'), ShippingMethods::keys());
        $this->assertSame($entries[0]['key'], ShippingMethods::default()->key);

        foreach ($entries as $index => $entry) {
            $this->assertSame($entry['key'], $methods[$index]->key);
            $this->assertSame($entry['name'], $methods[$index]->name);
            $this->assertSame($entry['description'] ?? null, $methods[$index]->description);
            $this->assertSame($entry['price_cents'], $methods[$index]->priceCents);
        }
    }

    public function test_find_returns_the_method_by_key(): void
    {
        config(['shipping' => [
            ['key' => 'selbstabholung', 'name' => 'Selbstabholung', 'price_cents' => 0],
            ['key' => 'standard', 'name' => 'Standardversand', 'description' => 'DPD', 'price_cents' => 900],
        ]]);

        $standard = ShippingMethods::find('standard');

        $this->assertNotNull($standard);
        $this->assertSame('Standardversand', $standard->name);
        $this->assertSame('DPD', $standard->description);
        $this->assertSame(900, $standard->priceCents);
        $this->assertNull(ShippingMethods::find('gratis'));
        $this->assertNull(ShippingMethods::find(''));
        $this->assertNull(ShippingMethods::find(null));
        $this->assertSame('selbstabholung', ShippingMethods::default()->key);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidConfigs(): array
    {
        $valid = ['key' => 'standard', 'name' => 'Standardversand', 'price_cents' => 900];

        return [
            'leere Liste' => [[]],
            'keine Liste' => [['standard' => $valid]],
            'kein Array' => [null],
            'Eintrag ist kein Array' => [['standard']],
            'doppelter Key' => [[$valid, [...$valid, 'name' => 'Noch einmal']]],
            'Key fehlt' => [[['name' => 'Standardversand', 'price_cents' => 900]]],
            'Key leer' => [[[...$valid, 'key' => '']]],
            'Key mit Großbuchstaben' => [[[...$valid, 'key' => 'Standard']]],
            'Key mit Leerzeichen' => [[[...$valid, 'key' => 'dpd standard']]],
            'Key als Zahl' => [[[...$valid, 'key' => 2]]],
            'Key zu lang' => [[[...$valid, 'key' => str_repeat('a', ShippingMethods::MAX_KEY_LENGTH + 1)]]],
            'Name fehlt' => [[['key' => 'standard', 'price_cents' => 900]]],
            'Name leer' => [[[...$valid, 'name' => '   ']]],
            'Name zu lang' => [[[...$valid, 'name' => str_repeat('a', ShippingMethods::MAX_NAME_LENGTH + 1)]]],
            'Beschreibung kein Text' => [[[...$valid, 'description' => ['DPD']]]],
            'Beschreibung zu lang' => [[[...$valid, 'description' => str_repeat('a', ShippingMethods::MAX_DESCRIPTION_LENGTH + 1)]]],
            'Preis fehlt' => [[['key' => 'standard', 'name' => 'Standardversand']]],
            'Preis null (auf Anfrage)' => [[[...$valid, 'price_cents' => null]]],
            'Preis negativ' => [[[...$valid, 'price_cents' => -1]]],
            'Preis als Float' => [[[...$valid, 'price_cents' => 9.0]]],
            'Preis in Euro als Float' => [[[...$valid, 'price_cents' => 9.5]]],
            'Preis als String' => [[[...$valid, 'price_cents' => '900']]],
            'Preis als bool' => [[[...$valid, 'price_cents' => true]]],
            'altes Feld price statt price_cents' => [[['key' => 'standard', 'name' => 'Standardversand', 'price' => '9.00']]],
            'unbekanntes Feld' => [[[...$valid, 'sort_order' => 1]]],
            'zweiter Eintrag fehlerhaft' => [[$valid, ['key' => 'premium', 'name' => 'Premium', 'price_cents' => -2500]]],
        ];
    }

    #[DataProvider('invalidConfigs')]
    public function test_invalid_config_is_detected(mixed $entries): void
    {
        $this->assertNotSame([], ShippingMethods::errors($entries));
    }

    #[DataProvider('invalidConfigs')]
    public function test_invalid_config_is_never_used(mixed $entries): void
    {
        config(['shipping' => $entries]);

        $this->expectException(UnexpectedValueException::class);

        ShippingMethods::all();
    }

    public function test_minimal_and_free_entries_are_valid(): void
    {
        $entries = [
            ['key' => 'selbstabholung', 'name' => 'Selbstabholung', 'price_cents' => 0],
            ['key' => 'dpd-premium-2', 'name' => 'Premium', 'description' => null, 'price_cents' => 2500],
        ];

        $this->assertSame([], ShippingMethods::errors($entries));
    }
}
