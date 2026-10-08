<?php

namespace App\Http\Middleware;

use App\Support\Cart\CartService;
use App\Support\Catalog\Categories;
use App\Support\Orders\OrderStatus;
use App\Support\PaymentMode;
use App\Support\ShopContact;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $cart = app(CartService::class)->cart()->toArray();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            // Absolute Basis für Canonical-/og:url-Tags: im SSR gibt es kein
            // window.location, und og:url verlangt absolute URLs (V4 SEO-1).
            'appUrl' => config('app.url'),
            'auth' => [
                'user' => $request->user(),
                'admin' => $request->user('admin'),
            ],
            'cart' => $cart,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'contact' => app(ShopContact::class)->toArray(),
            // Feste Kategorien aus config/categories.php, in deren Reihenfolge.
            'navCategories' => Inertia::always(fn () => Categories::options()),
            'sandbox' => ! PaymentMode::isLive(),
            // Label und Ton je Bestellstatus, für Badges in Admin und Kundenkonto.
            'orderStatuses' => OrderStatus::badges(),
        ];
    }
}
