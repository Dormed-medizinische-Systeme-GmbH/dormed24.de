<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function show(Request $request, string $category, Product $product): Response
    {
        // Die URL trägt die Kategorie: Produkt und Kategorie müssen zusammenpassen.
        abort_unless($product->category_slug === $category, 404);

        $product->load(['manufacturer', 'ratings', 'images', 'variants']);
        $product->loadAvg('ratings', 'stars');

        $similarProducts = Product::available()
            ->where('category_slug', $product->category_slug)
            ->whereKeyNot($product->id)
            ->with(['manufacturer', 'images' => fn ($query) => $query->where('sort_order', 0)])
            ->orderBy('name')
            ->get()
            ->values();

        return Inertia::render('Products/Show', [
            'product' => array_merge($product->toArray(), [
                'category' => $product->category()?->toArray(),
                'technical_specs' => $product->technical_specs ?? [],
                'specialties' => array_map(
                    fn ($specialty) => $specialty->toArray(),
                    $product->specialtyList(),
                ),
                'images' => $product->images->map(fn ($img) => [
                    'id' => $img->id,
                    'url' => $img->url,
                    'sort_order' => $img->sort_order,
                ])->values(),
                'variants' => $product->variants->map(fn ($v) => [
                    'id' => $v->id,
                    'label' => $v->label,
                    'price' => $v->price,
                    'is_default' => $v->is_default,
                ])->values(),
            ]),
            'ratings' => $product->ratings->map(fn ($rating) => [
                'id' => $rating->id,
                'stars' => $rating->stars,
                'content' => $rating->content,
                'created_at' => $rating->created_at?->format('d.m.Y'),
                'verified' => $rating->isVerifiedPurchase(),
            ])->values(),
            'ratingSummary' => [
                'average' => $product->ratings_avg_stars !== null
                    ? number_format((float) $product->ratings_avg_stars, 1, ',', '.')
                    : null,
                'count' => $product->ratings->count(),
            ],
            // Formular nur für eingeloggte Kunden mit bezahltem Kauf, die
            // noch nicht bewertet haben.
            'canRate' => $request->user() instanceof Customer && $request->user()->canRate($product),
            'similarProducts' => $similarProducts,
        ]);
    }
}
