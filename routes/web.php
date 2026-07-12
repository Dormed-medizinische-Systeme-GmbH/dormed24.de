<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

// TEMPORÄR: Deploy-Validierung — existiert erst nach dem nächsten erfolgreichen
// Deploy auf dem Server. Antwortet "OK" (200). Nach der Validierung wieder entfernen.
Route::get('/deploy-check', fn () => response('OK', 200)->header('Content-Type', 'text/plain'));

Route::get('/', HomeController::class)->name('home');

Route::prefix('informationen')->group(function () {
    Route::inertia('/versand', 'VersandUndZahlung')->name('versand');
    Route::inertia('/zahlung', 'Zahlung')->name('zahlung');
    Route::inertia('/faq', 'FAQ')->name('faq');
    Route::inertia('/widerrufsbelehrung', 'Widerrufsbelehrung')->name('widerrufsbelehrung');
});

Route::prefix('unternehmen')->group(function () {
    Route::inertia('/impressum', 'Impressum')->name('impressum');
    Route::inertia('/agb', 'AGB')->name('agb');
    Route::inertia('/datenschutz', 'Datenschutz')->name('datenschutz');
});

require __DIR__.'/admin.php';
require __DIR__.'/products.php';
require __DIR__.'/public/rating.php';
require __DIR__.'/checkout.php';
require __DIR__.'/settings.php';
require __DIR__.'/categories.php';
require __DIR__.'/paypal.php';
