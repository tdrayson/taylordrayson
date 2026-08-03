<?php

namespace App\Queries\Lookups;

use App\Models\Checkin;
use App\Services\Mapbox;

/**
 * Places for the location field. Picking one fills the coordinates as well as
 * the name, so a saved entry has what the map generator needs.
 */
final class PlaceLookup
{
    public function __construct(private Mapbox $mapbox) {}

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
            'fill' => [
                'latitude' => $place['latitude'],
                'longitude' => $place['longitude'],
            ],
        ], $this->mapbox->search($query, $latitude, $longitude)));
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
