<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\Catalog\Categories;
use App\Support\Products\ProductFilters;
use App\Support\Products\ProductSort;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function show(Request $request, string $category): Response
    {
        $definition = Categories::find($category) ?? abort(404);

        [$sort, $column, $direction] = ProductSort::fromRequest($request);

        $scope = Product::available()->where('category_slug', $definition->slug);

        $filters = ProductFilters::fromRequest($request, $scope);

        $baseQuery = $filters->apply($scope->clone())
            ->with(['manufacturer', 'images' => fn ($q) => $q->where('sort_order', 0)])
            ->orderBy($column, $direction)
            ->orderBy('id');

        $total = $baseQuery->count();

        return Inertia::render('Products/ByCategory', [
            'category' => $definition->toArray(),
            'products' => Inertia::scroll(fn () => $baseQuery->paginate(24)),
            'total' => $total,
            'sort' => $sort,
            ...$filters->toProps(),
        ]);
    }
}
