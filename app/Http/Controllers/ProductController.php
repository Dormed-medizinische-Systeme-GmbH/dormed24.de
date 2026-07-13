<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\Products\ProductSort;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * Name-Teilstringsuche mit escapten LIKE-Wildcards: `%` und `_` im
     * Suchbegriff werden literal gematcht, nicht als Platzhalter (verhindert,
     * dass z. B. "%" alle Produkte zieht). ESCAPE-Klausel für Portabilität
     * MySQL/SQLite.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    private function whereNameLike(Builder $query, string $term): Builder
    {
        return $query->whereRaw('name LIKE ? ESCAPE ?', [
            '%'.addcslashes($term, '%_\\').'%',
            '\\',
        ]);
    }

    public function index(Request $request): Response
    {
        $query = $request->string('q')->trim();
        [$sort, $column, $direction] = ProductSort::fromRequest($request);

        $baseQuery = Product::available()
            ->with(['manufacturer', 'images' => fn ($q) => $q->where('sort_order', 0)])
            ->when($query->isNotEmpty(), fn ($q) => $this->whereNameLike($q, $query->toString()))
            ->orderBy($column, $direction)
            ->orderBy('id');

        $total = $baseQuery->count();

        return Inertia::render('Products/Index', [
            'products' => Inertia::scroll(fn () => $baseQuery->paginate(24)),
            'total' => $total,
            'query' => $query->toString(),
            'sort' => $sort,
        ]);
    }

    public function show(Product $product): Response
    {
        $product->load(['category', 'manufacturer', 'ratings', 'images', 'variants']);
        $product->loadAvg('ratings', 'stars');

        $randomProducts = Product::available()
            ->whereKeyNot($product->id)
            ->with(['images' => fn ($query) => $query->where('sort_order', 0)])
            ->inRandomOrder()
            ->limit(8)
            ->get(['id', 'name', 'price'])
            ->map(fn (Product $randomProduct) => [
                'id' => $randomProduct->id,
                'name' => $randomProduct->name,
                'price' => $randomProduct->price,
                'image_url' => $randomProduct->images->first()?->url,
            ])
            ->values();

        return Inertia::render('Products/Show', [
            'product' => array_merge($product->toArray(), [
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
            ])->values(),
            'ratingSummary' => [
                'average' => $product->ratings_avg_stars !== null
                    ? number_format((float) $product->ratings_avg_stars, 1, ',', '.')
                    : null,
                'count' => $product->ratings->count(),
            ],
            'randomProducts' => $randomProducts,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->string('q')->trim();

        if ($query->isEmpty()) {
            return response()->json(['results' => [], 'total' => 0]);
        }

        $results = $this->whereNameLike(
            Product::available()->with(['images' => fn ($q) => $q->where('sort_order', 0)]),
            $query->toString(),
        )
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'name', 'price'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'image_url' => $product->images->first()?->url,
            ]);

        $total = $this->whereNameLike(Product::available(), $query->toString())->count();

        return response()->json([
            'results' => $results,
            'total' => $total,
        ]);
    }
}
