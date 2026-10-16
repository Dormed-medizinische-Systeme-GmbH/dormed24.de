<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Die Migration läuft beim Deploy gegen echte Bestellungen: Sie darf nur
 * eindeutige Beträge zuordnen und vorhandene Snapshots nie überschreiben.
 */
class ShippingMethodBackfillTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_16_090000_backfill_shipping_method_snapshot_on_orders.php');
    }

    private function legacyOrder(string $shippingAmount): Order
    {
        return Order::factory()->create([
            'shipping_amount' => $shippingAmount,
            'shipping_method_key' => null,
            'shipping_method_name' => null,
        ]);
    }

    public function test_legacy_orders_get_the_method_matching_their_amount(): void
    {
        $pickup = $this->legacyOrder('0.00');
        $standard = $this->legacyOrder('9.00');
        $premium = $this->legacyOrder('25.00');

        $this->migration()->up();

        $this->assertSame(['selbstabholung', 'Selbstabholung'], [$pickup->refresh()->shipping_method_key, $pickup->shipping_method_name]);
        $this->assertSame(['standard', 'Standardversand (DPD)'], [$standard->refresh()->shipping_method_key, $standard->shipping_method_name]);
        $this->assertSame(['premium', 'Premium-Versand (DPD)'], [$premium->refresh()->shipping_method_key, $premium->shipping_method_name]);
    }

    public function test_orders_with_an_unknown_amount_stay_without_method(): void
    {
        $unknown = $this->legacyOrder('12.50');

        $this->migration()->up();

        $this->assertNull($unknown->refresh()->shipping_method_key);
        $this->assertNull($unknown->shipping_method_name);
    }

    public function test_existing_snapshots_are_never_overwritten(): void
    {
        $order = Order::factory()->create([
            'shipping_amount' => '9.00',
            'shipping_method_key' => 'express',
            'shipping_method_name' => 'Express',
        ]);

        $this->migration()->up();

        $this->assertSame('express', $order->refresh()->shipping_method_key);
        $this->assertSame('Express', $order->shipping_method_name);
    }

    public function test_running_it_twice_changes_nothing(): void
    {
        $order = $this->legacyOrder('25.00');

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame('premium', $order->refresh()->shipping_method_key);
        $this->assertSame(1, Order::query()->where('shipping_method_key', 'premium')->count());
    }
}
