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
     * @return array{0: string, 1: string, 2: 'asc'|'desc'} [sortKey, column, direction]
     */
    public static function fromRequest(Request $request): array
    {
        $sort = (string) $request->input('sort', 'name_asc');

        return match ($sort) {
            'name_desc' => [$sort, 'name', 'desc'],
            'price_asc' => [$sort, 'price', 'asc'],
            'price_desc' => [$sort, 'price', 'desc'],
            // Unbekannte Werte nicht ans Frontend zurückgeben: die
            // Sortierauswahl zeigte sonst nichts an.
            default => ['name_asc', 'name', 'asc'],
        };
    }
}
