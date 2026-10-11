<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\Products\ProductSort;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Produktsuche unter /search: JSON für die Header-Suche, sonst die
 * Ergebnisseite (noindex, ohne Filter — die Filter gehören zu den Kategorien).
 */
class SearchController extends Controller
{
    private const SUGGESTION_LIMIT = 5;

    public function index(Request $request): Response|JsonResponse
    {
        $term = $request->string('q')->trim()->toString();

        if ($request->expectsJson()) {
            return $this->suggestions($term);
        }

        [$sort, $column, $direction] = ProductSort::fromRequest($request);

        $baseQuery = Product::available()
            ->when(
                $term !== '',
                fn ($query) => $query->nameLike($term),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->with(['manufacturer', 'images' => fn ($q) => $q->where('sort_order', 0)])
            ->withAvg('ratings', 'stars')
            ->withCount('ratings')
            ->orderBy($column, $direction)
            ->orderBy('id');

        $total = $baseQuery->count();

        return Inertia::render('Products/Search', [
            'products' => Inertia::scroll(fn () => $baseQuery->paginate(24)),
            'total' => $total,
            'query' => $term,
            'sort' => $sort,
        ]);
    }

    private function suggestions(string $term): JsonResponse
    {
        if ($term === '') {
            return response()->json(['results' => [], 'total' => 0]);
        }

        $results = Product::available()
            ->nameLike($term)
            ->with(['images' => fn ($q) => $q->where('sort_order', 0)])
            ->orderBy('name')
            ->limit(self::SUGGESTION_LIMIT)
            ->get(['id', 'name', 'slug', 'category_slug', 'price'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'category_slug' => $product->category_slug,
                'price' => $product->price,
                'image_url' => $product->images->first()?->url,
            ]);

        return response()->json([
            'results' => $results,
            'total' => Product::available()->nameLike($term)->count(),
        ]);
    }
}
