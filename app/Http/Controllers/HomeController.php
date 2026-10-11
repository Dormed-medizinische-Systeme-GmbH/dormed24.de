<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $randomProducts = Product::available()
            ->with(['images' => fn ($query) => $query->where('sort_order', 0)])
            ->inRandomOrder()
            ->limit(8)
            ->get(['id', 'name', 'slug', 'category_slug', 'price'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'category_slug' => $product->category_slug,
                'price' => $product->price,
                'image_url' => $product->images->first()?->url,
            ])
            ->values();

        return Inertia::render('Welcome', [
            'randomProductsTitle' => 'Entdecken Sie unser Sortiment',
            'randomProducts' => $randomProducts,
        ]);
    }
}
