<?php

namespace Tests\Feature\Console;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Support\Orders\OrderStatus;
use App\Support\Orders\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Datenumzug SQLite → aktuelle Datenbank. In der CI ist das Ziel Postgres,
 * lokal SQLite im Speicher; beide Wege müssen funktionieren.
 */
class ImportSqliteDatabaseTest extends TestCase
{
    use RefreshDatabase;

    private string $sourcePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourcePath = tempnam(sys_get_temp_dir(), 'import-').'.sqlite';
        touch($this->sourcePath);

        config(['database.connections.source' => [
            'driver' => 'sqlite',
            'database' => $this->sourcePath,
            'foreign_key_constraints' => true,
        ]]);

        Artisan::call('migrate', ['--database' => 'source', '--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::purge('source');
        DB::purge('sqlite_import');
        @unlink($this->sourcePath);

        parent::tearDown();
    }

    /**
     * Kunde mit Bestellung und Zahlung (JSON) sowie ein nicht verfügbares
     * Produkt (Boolean false) in der Quelle.
     */
    private function seedSource(): void
    {
        $source = DB::connection('source');

        $source->table('customers')->insert([
            'id' => 7,
            'name' => 'Praxis Müller',
            'email' => 'mueller@example.com',
            'password' => 'x',
            'created_at' => '2026-10-01 10:00:00',
            'updated_at' => '2026-10-01 10:00:00',
        ]);
        $source->table('orders')->insert([
            'id' => 42,
            'customer_id' => 7,
            'status' => 'paid',
            'payment_method' => 'paypal',
            'is_test' => 0,
            'total_amount' => '119.00',
            'shipping_amount' => '0.00',
            'shipping_address' => json_encode(['city' => 'Dortmund']),
            'created_at' => '2026-10-01 10:00:00',
            'updated_at' => '2026-10-01 10:00:00',
        ]);
        $source->table('payments')->insert([
            'order_id' => 42,
            'paypal_order_id' => 'ORDER-1',
            'status' => 'COMPLETED',
            'amount' => '119.00',
            'currency' => 'EUR',
            'response_data' => json_encode(['id' => 'ORDER-1']),
            'created_at' => '2026-10-01 10:00:00',
            'updated_at' => '2026-10-01 10:00:00',
        ]);

        // manufacturer_id explizit null: sonst legt die Factory einen
        // Hersteller im Ziel an, und das ist dann nicht mehr leer.
        $product = Product::factory()->make(['is_available' => false, 'manufacturer_id' => null]);
        $source->table('products')->insert([
            ...$product->getAttributes(),
            'id' => 5,
            'is_available' => 0,
            'created_at' => '2026-10-01 10:00:00',
            'updated_at' => '2026-10-01 10:00:00',
        ]);
    }

    public function test_copies_all_rows_with_types_and_relations(): void
    {
        $this->seedSource();

        $this->artisan('db:import-sqlite', ['path' => $this->sourcePath])
            ->expectsOutputToContain('alle Zeilenzahlen stimmen')
            ->assertSuccessful();

        $order = Order::query()->with(['customer', 'payments'])->findOrFail(42);
        $this->assertSame('Praxis Müller', $order->customer?->name);
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('Dortmund', $order->shipping_address['city'] ?? null);
        $this->assertSame(PaymentStatus::Completed, $order->payments->sole()->status);
        $this->assertSame('ORDER-1', $order->payments->sole()->response_data['id'] ?? null);
        $this->assertFalse(Product::query()->findOrFail(5)->is_available);
    }

    public function test_new_records_continue_after_the_imported_ids(): void
    {
        $this->seedSource();

        $this->artisan('db:import-sqlite', ['path' => $this->sourcePath])->assertSuccessful();

        $this->assertGreaterThan(7, Customer::factory()->create()->id);
        $this->assertGreaterThan(42, Order::factory()->create()->id);
    }

    public function test_refuses_a_target_that_already_has_data(): void
    {
        $this->seedSource();
        Customer::factory()->create();

        $this->artisan('db:import-sqlite', ['path' => $this->sourcePath])
            ->expectsOutputToContain('customers: enthält im Ziel bereits Daten')
            ->assertFailed();

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_refuses_a_missing_file(): void
    {
        $this->artisan('db:import-sqlite', ['path' => '/gibt/es/nicht.sqlite'])
            ->expectsOutputToContain('nicht gefunden')
            ->assertFailed();
    }
}
