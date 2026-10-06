<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Support\Cart\CartService;
use App\Support\Catalog\Categories;
use App\Support\PaymentMode;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    private const DEFAULT_SHOP_EMAIL = 'mail@dormed.de';

    private const DEFAULT_SHOP_PHONE = '02301 – 188600';

    private const DEFAULT_SHOP_FAX = '02301 / 188-620';

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
        $phone = Setting::get('shop.phone', self::DEFAULT_SHOP_PHONE) ?? self::DEFAULT_SHOP_PHONE;
        $fax = Setting::get('shop.fax', self::DEFAULT_SHOP_FAX) ?? self::DEFAULT_SHOP_FAX;

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
            'contact' => [
                'email' => Setting::get('shop.email', self::DEFAULT_SHOP_EMAIL) ?? self::DEFAULT_SHOP_EMAIL,
                'phone' => $phone,
                'fax' => $fax,
                'phone_href' => $this->phoneHref($phone),
                'fax_href' => $this->phoneHref($fax),
                'phone_display' => $this->phoneDisplay($phone),
                'fax_display' => $this->phoneDisplay($fax),
            ],
            // Feste Kategorien aus config/categories.php, in deren Reihenfolge.
            'navCategories' => Inertia::always(fn () => Categories::options()),
            'sandbox' => ! PaymentMode::isLive(),
        ];
    }

    /**
     * Anzeigeformat "02301 / 188-600": Vorwahl, Schrägstrich, Rufnummer mit
     * Bindestrich vor den letzten drei Ziffern. Die Vorwahl ist der erste
     * Ziffernblock der Eingabe, +49/0049 wird zur 0. Lässt sich keine Vorwahl
     * abtrennen, bleibt der Wert unverändert.
     */
    private function phoneDisplay(string $value): string
    {
        $national = preg_replace('/^\s*(\+|00)49[\s\-\/–]*(\(0\))?\s*/u', '0', $value) ?? $value;

        if (! preg_match('/^\s*(\d+)\D+(.*)$/u', $national, $matches)) {
            return $value;
        }

        $areaCode = $matches[1];
        $subscriber = preg_replace('/\D/', '', $matches[2]) ?? '';

        if (strlen($subscriber) < 4) {
            return $value;
        }

        return $areaCode.' / '.substr($subscriber, 0, -3).'-'.substr($subscriber, -3);
    }

    private function phoneHref(string $value): string
    {
        $normalized = preg_replace('/[^\d+]/', '', $value) ?? '';

        if (str_starts_with($normalized, '00')) {
            return 'tel:+'.substr($normalized, 2);
        }

        if (str_starts_with($normalized, '+')) {
            return 'tel:'.$normalized;
        }

        if (str_starts_with($normalized, '0')) {
            return 'tel:+49'.ltrim($normalized, '0');
        }

        return 'tel:'.$normalized;
    }
}
