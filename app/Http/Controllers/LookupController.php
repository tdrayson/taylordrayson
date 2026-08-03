<?php

namespace App\Http\Controllers;

use App\Queries\Lookups\AirlineLookup;
use App\Queries\Lookups\AirportLookup;
use App\Queries\Lookups\BookLookup;
use App\Queries\Lookups\PlaceLookup;
use App\Services\Mapbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Autocomplete for the fields that cannot be a plain text box: airports,
 * airlines, books and places.
 *
 * One endpoint with a source segment rather than four controllers, since every
 * one answers the same question and returns the same shape. A result may carry
 * `fill`, which sets other fields on the form too: picking a book should not
 * mean retyping its author.
 */
class LookupController extends Controller
{
    public function __invoke(Request $request, string $source): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        $results = match ($source) {
            'airport' => app(AirportLookup::class)($query),
            'airline' => app(AirlineLookup::class)($query),
            'book' => app(BookLookup::class)($query),
            'place' => app(PlaceLookup::class)(
                $query,
                $request->float('lat') ?: null,
                $request->float('lng') ?: null,
            ),
            default => throw new NotFoundHttpException,
        };

        return response()->json(['data' => $results]);
    }

    /**
     * The place at a coordinate, for the "use my location" button. Separate
     * from the search above because the browser supplies a position rather
     * than a query, and there is exactly one answer.
     */
    public function reverse(Request $request, Mapbox $mapbox): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        return response()->json(['data' => $mapbox->reverse((float) $validated['lat'], (float) $validated['lng'])]);
    }
}
