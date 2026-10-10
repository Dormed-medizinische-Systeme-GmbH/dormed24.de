<?php

use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

// Antwortet je nach Anfrage mit der Ergebnisseite (noindex) oder JSON für
// die Header-Suche.
Route::get('/search', [SearchController::class, 'index'])->name('search');
