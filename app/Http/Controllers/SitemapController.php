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
                ['loc' => url('/products'), 'changefreq' => 'daily', 'priority' => '0.9'],
                ['loc' => route('faq'), 'changefreq' => 'monthly', 'priority' => '0.7'],
                ['loc' => route('agb'), 'changefreq' => 'monthly', 'priority' => '0.3'],
                ['loc' => route('impressum'), 'changefreq' => 'monthly', 'priority' => '0.3'],
                ['loc' => route('datenschutz'), 'changefreq' => 'monthly', 'priority' => '0.3'],
                ['loc' => route('widerrufsbelehrung'), 'changefreq' => 'monthly', 'priority' => '0.3'],
                ['loc' => route('versand'), 'changefreq' => 'monthly', 'priority' => '0.4'],
                ['loc' => route('zahlung'), 'changefreq' => 'monthly', 'priority' => '0.4'],
            ];

            $products = Product::select('id', 'updated_at')->get();
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
