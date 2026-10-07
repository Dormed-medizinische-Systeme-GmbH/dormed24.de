<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Support\Products\ProductFilters;
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

        $scope = Product::available()
            ->when($query->isNotEmpty(), fn ($q) => $this->whereNameLike($q, $query->toString()));

        $filters = ProductFilters::fromRequest($request, $scope);

        $baseQuery = $filters->apply($scope->clone())
            ->with(['manufacturer', 'images' => fn ($q) => $q->where('sort_order', 0)])
            ->orderBy($column, $direction)
            ->orderBy('id');

        $total = $baseQuery->count();

        return Inertia::render('Products/Index', [
            'products' => Inertia::scroll(fn () => $baseQuery->paginate(24)),
            'total' => $total,
            'query' => $query->toString(),
            'sort' => $sort,
            ...$filters->toProps(),
        ]);
    }

    public function show(Request $request, Product $product): Response
    {
        $product->load(['manufacturer', 'ratings', 'images', 'variants']);
        $product->loadAvg('ratings', 'stars');

        $similarProducts = $product->category_slug === null
            ? collect()
            : Product::available()
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
