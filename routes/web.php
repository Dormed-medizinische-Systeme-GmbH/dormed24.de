<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\MagazineController;
use App\Http\Controllers\SitemapController;
use App\Models\Product;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

Route::get('/', HomeController::class)->name('home');

// TEST: Landingpage-Kopie mit Hover-Mega-Menu-Header (siehe TODO.md).
// Zum Entfernen: diese Route + resources/js/pages/Test/Welcome.svelte +
// resources/js/components/TestAppFooter.svelte loeschen.
Route::get('/test', function () {
    $randomProducts = Product::available()
        ->with(['images' => fn ($query) => $query->where('sort_order', 0)])
        ->inRandomOrder()
        ->limit(8)
        ->get(['id', 'name', 'slug', 'category_slug', 'price'])
        ->map(fn (Product $product) => [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'category_slug' => $product->category_slug,
            'price' => $product->price,
            'image_url' => $product->images->first()?->url,
        ])
        ->values();

    return Inertia::render('Test/Welcome', [
        'randomProductsTitle' => 'Entdecken Sie unser Sortiment',
        'randomProducts' => $randomProducts,
    ]);
})->name('test.hover-header');

Route::prefix('informationen')->group(function () {
    Route::inertia('/versand', 'Informationen/VersandUndZahlung')->name('versand');
    Route::inertia('/zahlung', 'Informationen/Zahlung')->name('zahlung');
    Route::inertia('/faq', 'Informationen/FAQ')->name('faq');
    Route::inertia('/widerrufsbelehrung', 'Informationen/Widerrufsbelehrung')->name('widerrufsbelehrung');
});

// Magazin: bewusst noch nirgends verlinkt und nicht in der Sitemap.
Route::get('/magazin', [MagazineController::class, 'index'])->name('magazine.index');
Route::get('/magazin/{article:slug}', [MagazineController::class, 'show'])->name('magazine.show');

Route::prefix('unternehmen')->group(function () {
    Route::inertia('/impressum', 'Unternehmen/Impressum')->name('impressum');
    Route::inertia('/agb', 'Unternehmen/AGB')->name('agb');
    Route::inertia('/datenschutz', 'Unternehmen/Datenschutz')->name('datenschutz');
});

require __DIR__.'/admin.php';
require __DIR__.'/products.php';
require __DIR__.'/public/rating.php';
require __DIR__.'/checkout.php';
require __DIR__.'/settings.php';
require __DIR__.'/paypal.php';
// Zuletzt: Die Kategorie-/Produkt-Muster fangen sonst feste Pfade ab.
require __DIR__.'/categories.php';
