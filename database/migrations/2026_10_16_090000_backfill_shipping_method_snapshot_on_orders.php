<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Trägt den Versandarten-Snapshot für Bestellungen nach, die vor
 * 2026_10_14_090000 angelegt wurden. Die Versandart wird aus dem Betrag
 * abgeleitet. Das ist eindeutig, weil die drei Versandarten verschiedene
 * Preise hatten, die sich nie geändert haben.
 *
 * Die Zuordnung steht bewusst hier und nicht in config/shipping.php: Sie
 * beschreibt den Stand vor der Umstellung und darf sich mit späteren
 * Config-Änderungen nicht verschieben. Bestellungen mit anderem Betrag
 * bleiben ohne Versandart.
 */
return new class extends Migration
{
    /**
     * @var array<string, array{key: string, name: string}>
     */
    private const LEGACY_METHODS_BY_AMOUNT = [
        '0.00' => ['key' => 'selbstabholung', 'name' => 'Selbstabholung'],
        '9.00' => ['key' => 'standard', 'name' => 'Standardversand (DPD)'],
        '25.00' => ['key' => 'premium', 'name' => 'Premium-Versand (DPD)'],
    ];

    public function up(): void
    {
        foreach (self::LEGACY_METHODS_BY_AMOUNT as $amount => $method) {
            DB::table('orders')
                ->whereNull('shipping_method_key')
                ->whereNull('shipping_method_name')
                ->where('shipping_amount', $amount)
                ->update([
                    'shipping_method_key' => $method['key'],
                    'shipping_method_name' => $method['name'],
                ]);
        }
    }

    /**
     * Nachgetragene und echte Snapshots lassen sich nicht mehr unterscheiden.
     * Ein Rollback lässt die Daten deshalb stehen; die Spalten selbst entfernt
     * erst das Zurückrollen von 2026_10_14_090000.
     */
    public function down(): void {}
};
