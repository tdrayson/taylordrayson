<?php

namespace App\Http\Controllers;

use App\Queries\Lookups\AirlineLookup;
use App\Queries\Lookups\AirportLookup;
use App\Queries\Lookups\BookLookup;
use App\Queries\Lookups\FuelBrandLookup;
use App\Queries\Lookups\PlaceLookup;
use App\Queries\Lookups\StationLookup;
use App\Queries\Lookups\TagLookup;
use App\Queries\Lookups\TimezoneLookup;
use App\Services\GoogleMaps;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Autocomplete for airports, airlines, books and places: one endpoint with a
 * source segment, since each returns the same shape. A result may carry `fill`,
 * which populates other fields on the form too.
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
            'tag' => app(TagLookup::class)($query),
            'timezone' => app(TimezoneLookup::class)($query),
            'station' => app(StationLookup::class)($query, $request->float('lat') ?: null, $request->float('lng') ?: null),
            'fuel-brand' => app(FuelBrandLookup::class)($query),
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
    public function reverse(Request $request, GoogleMaps $maps): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $place = $maps->reverse((float) $validated['lat'], (float) $validated['lng']);

        return response()->json(['data' => $place === null ? null : [
            ...$place,
            // The street line is what `address` holds; the town, postcode and
            // country have fields of their own and repeating them there says
            // the same thing twice.
            'address' => $place['street'],
        ]]);
    }
}
