<?php

namespace Tests\Feature\Support;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Cart\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Schützt vor N+1 im Warenkorb: die Anzahl der Abfragen darf nicht mit der
 * Zahl der Positionen wachsen. Dass der Warenkorb inhaltlich stimmt, prüft
 * CartShapeTest — hier wird nur sichergestellt, dass die Daten beim
 * Zählen wirklich geladen wurden (sonst wäre "0 Abfragen" ein Scheinerfolg).
 */
class CartServiceQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_loads_products_in_one_query_each(): void
    {
        config(['shipping' => [
            ['key' => 'standard', 'name' => 'Standard', 'price_cents' => 490],
            ['key' => 'express', 'name' => 'Express', 'price_cents' => 490],
        ]]);

        $lines = [];

        foreach (range(1, 4) as $index) {
            $product = Product::factory()->create(['name' => "Produkt {$index}"]);
            $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
            $lines["{$product->id}:{$variant->id}"] = $index;
        }

        session()->put(['cart' => ['items' => $lines]]);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $cart = app(CartService::class)->cart();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(4, $cart->items);
        $this->assertSame(10, $cart->count());
        $this->assertCount(2, $cart->shippingMethods);

        $this->assertCount(1, $this->queriesTo($queries, 'products'));
        $this->assertCount(1, $this->queriesTo($queries, 'product_variants'));
    }

    public function test_shipping_methods_come_from_the_config_without_queries(): void
    {
        $service = app(CartService::class);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $first = $service->cart();
        $second = $service->cart();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertNotSame([], $first->shippingMethods);
        $this->assertEquals($first->shippingMethods, $second->shippingMethods);
        $this->assertSame([], $queries);
    }

    /**
     * @param  list<array{query: string}>  $queries
     * @return list<array{query: string}>
     */
    private function queriesTo(array $queries, string $table): array
    {
        return array_values(array_filter(
            $queries,
            fn (array $query): bool => preg_match('/\bfrom [`"\[]?'.preg_quote($table, '/').'[`"\]]?(\s|$)/i', $query['query']) === 1,
        ));
    }
}
