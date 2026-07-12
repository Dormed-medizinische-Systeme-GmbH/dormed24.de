<?php

namespace App\Support\Products;

use Illuminate\Http\Request;

/**
 * Shared sort resolution for the public product listings
 * (ProductController::index and CategoryController::show), so both accept
 * the same sort keys and fall back identically.
 */
class ProductSort
{
    /**
     * @return array{0: string, 1: string, 2: string} [sortKey, column, direction]
     */
    public static function fromRequest(Request $request): array
    {
        $sort = (string) $request->input('sort', 'name_asc');

        [$column, $direction] = match ($sort) {
            'name_desc' => ['name', 'desc'],
            'price_asc' => ['price', 'asc'],
            'price_desc' => ['price', 'desc'],
            default => ['name', 'asc'],
        };

        return [$sort, $column, $direction];
    }
}
