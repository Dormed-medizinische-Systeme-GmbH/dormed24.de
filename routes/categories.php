<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Kategorien und Produkte
|--------------------------------------------------------------------------
| Muss als letzte Datei geladen werden: Beide Muster passen auf beliebige
| Pfade und dürfen keine festen Routen überdecken. Die Slug-Muster
| enthalten keinen Punkt, damit Dateipfade (…/bild.jpg) nie passen.
| Unbekannte Kategorien und Produkte liefern 404.
*/

Route::get('/{category}', [CategoryController::class, 'show'])
    ->name('category.show')
    ->where('category', '[a-z0-9][a-z0-9\-]*');

Route::get('/{category}/{product:slug}', [ProductController::class, 'show'])
    ->name('products.show')
    ->where([
        'category' => '[a-z0-9][a-z0-9\-]*',
        'product' => '[a-z0-9][a-z0-9\-]*',
    ]);
