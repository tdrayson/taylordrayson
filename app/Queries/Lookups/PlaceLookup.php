<?php

namespace App\Queries\Lookups;

use App\Models\Checkin;
use App\Services\GoogleMaps;

/**
 * Places for the location field, from Google.
 *
 * Google rather than Mapbox: Mapbox ranked streets above businesses, so
 * searching for a venue returned the road it stands on. Mapbox still renders
 * every stored map; this is only about finding the place.
 */
final class PlaceLookup
{
    public function __construct(private GoogleMaps $maps) {}

    /**
     * @return list<array{value: string, label: string, detail: string|null, fill: array<string, mixed>}>
     */
    public function __invoke(string $query, ?float $latitude = null, ?float $longitude = null): array
    {
        // Without a hint Mapbox ranks globally: "shell caterham" came back as a
        // filling station in Chile. The last place checked into is the best
        // guess at where the search is being made from, and it follows you
        // abroad rather than pinning results to home forever.
        if ($latitude === null || $longitude === null) {
            [$latitude, $longitude] = $this->lastKnownPosition();
        }

        return array_values(array_map(fn (array $place): array => [
            'value' => $place['name'],
            'label' => $place['name'],
            'detail' => $place['address'],
            // Everything the pick resolved, so a type that stores a city or a
            // postcode gets them filled rather than only its coordinates.
            // EntryEditor writes only the keys the form actually has.
            // Coordinates only. Text Search returns no address components, so
            // the parts are resolved by reverse-geocoding this position when
            // the row is picked, rather than by an extra call per row in a
            // list that may never be chosen from.
            'fill' => array_filter([
                'latitude' => $place['latitude'],
                'longitude' => $place['longitude'],
            ], fn ($value): bool => $value !== null && $value !== ''),
        ], $this->maps->search($query, $latitude, $longitude)));
    }

    /**
     * @return array{0: float|null, 1: float|null}
     */
    private function lastKnownPosition(): array
    {
        $checkin = Checkin::query()
            ->whereNotNull('latitude')
            ->orderByDesc('occurred_at')
            ->first(['latitude', 'longitude']);

        return $checkin === null
            ? [null, null]
            : [(float) $checkin->latitude, (float) $checkin->longitude];
    }
}
