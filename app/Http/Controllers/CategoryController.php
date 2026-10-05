<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Support\Products\ProductFilters;
use App\Support\Products\ProductSort;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function show(Request $request, Category $category): Response
    {
        [$sort, $column, $direction] = ProductSort::fromRequest($request);

        $scope = Product::available()->whereBelongsTo($category);

        $filters = ProductFilters::fromRequest($request, $scope);

        $baseQuery = $filters->apply($scope->clone())
            ->with(['manufacturer', 'images' => fn ($q) => $q->where('sort_order', 0)])
            ->orderBy($column, $direction)
            ->orderBy('id');

        $total = $baseQuery->count();

        return Inertia::render('Products/ByCategory', [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
            ],
            'products' => Inertia::scroll(fn () => $baseQuery->paginate(24)),
            'total' => $total,
            'sort' => $sort,
            ...$filters->toProps(),
        ]);
    }
}
