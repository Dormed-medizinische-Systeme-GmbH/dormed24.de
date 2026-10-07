<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rating;
use Illuminate\Http\RedirectResponse;

/**
 * Bewertungen sind sofort öffentlich; der Admin kann unpassende löschen.
 */
class RatingController extends Controller
{
    public function destroy(Rating $rating): RedirectResponse
    {
        $rating->delete();

        return back()->with('success', 'Bewertung gelöscht.');
    }
}
