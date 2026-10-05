<?php

use App\Http\Controllers\CategoryController;
use Illuminate\Support\Facades\Route;

Route::get('/{category}', [CategoryController::class, 'show'])
    ->name('category.show')
    ->where('category', '[a-z0-9][a-z0-9\-]*');
