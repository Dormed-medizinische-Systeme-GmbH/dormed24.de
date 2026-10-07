<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRatingRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Rating;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Bewertungen sind an Käufe gebunden (siehe StoreRatingRequest) und sofort
 * sichtbar. Abgegeben werden sie auf der Produktseite und in den
 * Bestelldetails, beide per JSON.
 */
class RatingController extends Controller
{
    public function store(StoreRatingRequest $request, Product $product): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        try {
            /** @var Rating $rating */
            $rating = $product->ratings()->create([
                ...$request->validated(),
                'customer_id' => $customer->id,
                'order_id' => $customer->paidOrderContaining($product)?->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Zwei gleichzeitige Absendungen: die zweite scheitert am Unique-Index.
            throw ValidationException::withMessages(['rating' => 'Sie haben dieses Produkt bereits bewertet.']);
        }

        return response()->json([
            'message' => 'Danke für Ihre Bewertung.',
            'rating' => [
                'id' => $rating->id,
                'stars' => $rating->stars,
                'content' => $rating->content,
            ],
        ], 201);
    }
}
