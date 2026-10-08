<?php

use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\ManufacturerController;
use App\Http\Controllers\Admin\NewsletterController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ProductImageController;
use App\Http\Controllers\Admin\ProductVariantController;
use App\Http\Controllers\Admin\RatingController as AdminRatingController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\ShippingMethodController;
use App\Http\Controllers\Admin\SocialPostController;
use Illuminate\Routing\RedirectController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:admin')->group(function () {
    Route::get('/admin/login', [LoginController::class, 'showLoginForm'])->name('admin.login');
    Route::post('/admin/login', [LoginController::class, 'login'])->name('admin.login.store')->middleware('throttle:6,1');
});

Route::post('/admin/logout', [LoginController::class, 'logout'])
    ->middleware('ensure.admin')
    ->name('admin.logout');

Route::middleware('ensure.admin')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [AdminProductController::class, 'create'])->name('products.create');
    Route::post('/products', [AdminProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [AdminProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [AdminProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [AdminProductController::class, 'destroy'])->name('products.destroy');
    Route::post('/products/{product}/images', [ProductImageController::class, 'store'])->name('products.images.store');
    Route::delete('/products/{product}/images/{image}', [ProductImageController::class, 'destroy'])->name('products.images.destroy');
    Route::patch('/products/{product}/images/reorder', [ProductImageController::class, 'reorder'])->name('products.images.reorder');
    Route::post('/products/{product}/variants', [ProductVariantController::class, 'store'])->name('products.variants.store');
    Route::delete('/ratings/{rating}', [AdminRatingController::class, 'destroy'])->name('ratings.destroy');
    Route::put('/products/{product}/variants/{variant}', [ProductVariantController::class, 'update'])->name('products.variants.update');
    Route::delete('/products/{product}/variants/{variant}', [ProductVariantController::class, 'destroy'])->name('products.variants.destroy');
    Route::get('/customers', [AdminCustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/{customer}', [AdminCustomerController::class, 'show'])->name('customers.show');
    Route::get('/newsletters', [NewsletterController::class, 'index'])->name('newsletters.index');
    Route::post('/newsletters', [NewsletterController::class, 'store'])->name('newsletters.store');
    Route::post('/newsletters/assets', [NewsletterController::class, 'uploadAsset'])->name('newsletters.assets.store');
    Route::get('/newsletters/{newsletter}/edit', [NewsletterController::class, 'edit'])->name('newsletters.edit');
    Route::get('/newsletters/{newsletter}/content', [NewsletterController::class, 'content'])->name('newsletters.content');
    Route::put('/newsletters/{newsletter}', [NewsletterController::class, 'update'])->name('newsletters.update');
    Route::delete('/newsletters/{newsletter}', [NewsletterController::class, 'destroy'])->name('newsletters.destroy');
    Route::post('/newsletters/{newsletter}/test', [NewsletterController::class, 'sendTest'])->middleware('throttle:10,1')->name('newsletters.test');
    Route::get('/social-posts', [SocialPostController::class, 'index'])->name('social-posts.index');
    Route::get('/social-posts/create', [SocialPostController::class, 'create'])->name('social-posts.create');
    Route::post('/social-posts', [SocialPostController::class, 'store'])->name('social-posts.store');
    Route::get('/social-posts/{socialPost}/edit', [SocialPostController::class, 'edit'])->name('social-posts.edit');
    Route::put('/social-posts/{socialPost}', [SocialPostController::class, 'update'])->name('social-posts.update');
    Route::delete('/social-posts/{socialPost}', [SocialPostController::class, 'destroy'])->name('social-posts.destroy');
    Route::get('/manufacturers', [ManufacturerController::class, 'index'])->name('manufacturers.index');
    Route::get('/manufacturers/create', [ManufacturerController::class, 'create'])->name('manufacturers.create');
    Route::post('/manufacturers', [ManufacturerController::class, 'store'])->name('manufacturers.store');
    Route::get('/manufacturers/{manufacturer}/edit', [ManufacturerController::class, 'edit'])->name('manufacturers.edit');
    Route::put('/manufacturers/{manufacturer}', [ManufacturerController::class, 'update'])->name('manufacturers.update');
    Route::delete('/manufacturers/{manufacturer}', [ManufacturerController::class, 'destroy'])->name('manufacturers.destroy');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}/confirm-payment', [OrderController::class, 'confirmPayment'])->name('orders.confirm-payment');
    Route::post('/orders/{order}/refund', [OrderController::class, 'refund'])->name('orders.refund');
    // Nur GET/HEAD, siehe routes/settings.php.
    Route::get('/settings', RedirectController::class)->defaults('destination', '/admin/settings/general')->defaults('status', 302)->name('settings.index');
    Route::get('/settings/general', [SettingController::class, 'showGeneral'])->name('settings.general');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::get('/settings/shipping', [ShippingMethodController::class, 'index'])->name('settings.shipping');
    Route::post('/settings/shipping', [ShippingMethodController::class, 'store'])->name('settings.shipping.store');
    Route::put('/settings/shipping/{shippingMethod}', [ShippingMethodController::class, 'update'])->name('settings.shipping.update');
    Route::delete('/settings/shipping/{shippingMethod}', [ShippingMethodController::class, 'destroy'])->name('settings.shipping.destroy');
});
