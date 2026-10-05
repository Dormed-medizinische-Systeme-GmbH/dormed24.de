<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kategorien kommen ab jetzt aus config/categories.php statt aus der
 * categories-Tabelle. Produkte speichern den Kategorie-Slug direkt
 * (products.category_slug), die Zuordnung wird über den Slug übernommen.
 *
 * Bricht bewusst ab, wenn Produkte an einer Kategorie hängen, deren Slug
 * nicht in der Config steht — sonst ginge diese Zuordnung still verloren.
 * In dem Fall den Slug in config/categories.php ergänzen und neu deployen.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Erst prüfen, dann das Schema ändern: Bricht die Migration hier ab,
        // ist noch nichts verändert und sie kann nach dem Ergänzen der Config
        // einfach erneut laufen.
        $hasCategoryTable = Schema::hasTable('categories');

        if ($hasCategoryTable) {
            $configuredSlugs = array_column((array) config('categories', []), 'slug');

            $unknown = DB::table('categories')
                ->join('products', 'products.category_id', '=', 'categories.id')
                ->whereNotIn('categories.slug', $configuredSlugs)
                ->distinct()
                ->pluck('categories.slug')
                ->all();

            if ($unknown !== []) {
                throw new RuntimeException(
                    'Produkte hängen an Kategorien, die nicht in config/categories.php stehen: '
                    .implode(', ', $unknown).'. Slugs dort ergänzen und Migration erneut ausführen.'
                );
            }
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('category_slug')->nullable()->after('manufacturer_id')->index();
        });

        if ($hasCategoryTable) {
            foreach (DB::table('categories')->get(['id', 'slug']) as $category) {
                DB::table('products')
                    ->where('category_id', $category->id)
                    ->update(['category_slug' => $category->slug]);
            }
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::dropIfExists('categories');
    }

    public function down(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        foreach ((array) config('categories', []) as $category) {
            DB::table('categories')->insert([
                'name' => $category['name'],
                'slug' => $category['slug'],
                'description' => $category['description'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('manufacturer_id')->constrained()->nullOnDelete();
        });

        foreach (DB::table('categories')->get(['id', 'slug']) as $category) {
            DB::table('products')
                ->where('category_slug', $category->slug)
                ->update(['category_id' => $category->id]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['category_slug']);
            $table->dropColumn('category_slug');
        });
    }
};
