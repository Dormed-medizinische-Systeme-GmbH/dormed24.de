<?php

namespace Tests\Feature\Support;

use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Die Migration läuft beim Deploy gegen echte Daten: Zuordnungen müssen über
 * den Slug erhalten bleiben, unbekannte Slugs dürfen nicht still verloren gehen.
 */
class CategoryMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_05_150522_move_product_categories_to_config.php');
    }

    /**
     * Zustand vor der Migration herstellen: categories-Tabelle + category_id.
     */
    private function rollBack(): Migration
    {
        $migration = $this->migration();
        $migration->down();

        return $migration;
    }

    /**
     * Produkt im Zustand vor der Migration (ohne category_slug-Spalte): die
     * Factory würde die Spalte mitschreiben.
     */
    private function legacyProduct(string $name): Product
    {
        $id = DB::table('products')->insertGetId([
            'manufacturer_id' => Manufacturer::factory()->create()->id,
            'name' => $name,
            'slug' => $name,
            'description' => '',
            'price' => '10.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Product::query()->findOrFail($id);
    }

    private function categoryId(string $slug): int
    {
        return (int) DB::table('categories')->where('slug', $slug)->value('id');
    }

    public function test_product_categories_are_carried_over_by_slug(): void
    {
        $migration = $this->rollBack();

        $accessory = $this->legacyProduct('zubehoer-produkt');
        $consumable = $this->legacyProduct('verbrauch-produkt');
        $uncategorized = $this->legacyProduct('ohne-kategorie');
        DB::table('products')->where('id', $accessory->id)->update(['category_id' => $this->categoryId('zubehoer')]);
        DB::table('products')->where('id', $consumable->id)->update(['category_id' => $this->categoryId('verbrauchsartikel')]);

        $migration->up();

        $this->assertFalse(Schema::hasTable('categories'));
        $this->assertFalse(Schema::hasColumn('products', 'category_id'));
        $this->assertSame('zubehoer', $accessory->refresh()->category_slug);
        $this->assertSame('verbrauchsartikel', $consumable->refresh()->category_slug);
        $this->assertNull($uncategorized->refresh()->category_slug);
    }

    public function test_unknown_category_slug_aborts_before_changing_anything(): void
    {
        $migration = $this->rollBack();

        $legacyId = DB::table('categories')->insertGetId([
            'name' => 'Alte Gruppe',
            'slug' => 'alte-gruppe',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $product = $this->legacyProduct('altes-produkt');
        DB::table('products')->where('id', $product->id)->update(['category_id' => $legacyId]);

        try {
            $migration->up();
            $this->fail('Migration hätte abbrechen müssen.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('alte-gruppe', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasTable('categories'));
        $this->assertFalse(Schema::hasColumn('products', 'category_slug'));
        $this->assertSame($legacyId, (int) DB::table('products')->where('id', $product->id)->value('category_id'));
    }

    public function test_unknown_category_without_products_does_not_block(): void
    {
        $migration = $this->rollBack();

        DB::table('categories')->insert([
            'name' => 'Leere Gruppe',
            'slug' => 'leere-gruppe',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->up();

        $this->assertFalse(Schema::hasTable('categories'));
    }
}
