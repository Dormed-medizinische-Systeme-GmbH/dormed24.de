<?php

use App\Http\Controllers\RatingController;
use Illuminate\Support\Facades\Route;

// Nur eingeloggte Kunden mit einer bezahlten Bestellung, die das Produkt
// enthält (StoreRatingRequest::authorize), einmal pro Produkt. Gedrosselt,
// weil jede Bewertung sofort öffentlich ist.
Route::post('/products/{product}/ratings', [RatingController::class, 'store'])
    ->middleware(['auth', 'throttle:10,1'])
    ->name('ratings.store');
