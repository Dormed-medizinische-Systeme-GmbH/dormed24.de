<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\Catalog\Categories;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHour(), function () {
            $staticUrls = [
                ['loc' => url('/'), 'changefreq' => 'weekly', 'priority' => '1.0'],
                ['loc' => route('faq'), 'changefreq' => 'monthly', 'priority' => '0.7'],
                ['loc' => route('agb'), 'changefreq' => 'monthly', 'priority' => '0.3'],
                ['loc' => route('impressum'), 'changefreq' => 'monthly', 'priority' => '0.3'],
                ['loc' => route('datenschutz'), 'changefreq' => 'monthly', 'priority' => '0.3'],
                ['loc' => route('widerrufsbelehrung'), 'changefreq' => 'monthly', 'priority' => '0.3'],
                ['loc' => route('versand'), 'changefreq' => 'monthly', 'priority' => '0.4'],
                ['loc' => route('zahlung'), 'changefreq' => 'monthly', 'priority' => '0.4'],
            ];

            // Nur Produkte mit Shop-URL (Kategorie und Slug); nicht verfügbare
            // Produkte bleiben erreichbar und gehören deshalb dazu.
            $products = Product::query()
                ->whereNotNull('category_slug')
                ->whereNotNull('slug')
                ->get(['id', 'category_slug', 'slug', 'updated_at'])
                ->map(fn (Product $product): array => [
                    'loc' => (string) $product->url(),
                    'lastmod' => $product->updated_at->toAtomString(),
                ]);
            $categories = Categories::all();

            // Die XML-Deklaration steht nicht im Template: Bei short_open_tag=On
            // hielte PHP "<?xml" dort für einen PHP-Tag.
            return '<?xml version="1.0" encoding="UTF-8"?>'."\n".view('sitemap', [
                'staticUrls' => $staticUrls,
                'products' => $products,
                'categories' => $categories,
            ])->render();
        });

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
