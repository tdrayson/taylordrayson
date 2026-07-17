<?php

namespace App\Services;

use App\Services\PetrolFinder\FuelStationResult;
use Illuminate\Support\Facades\Http;

/**
 * Client for the PetrolFinder.uk fuel-station lookup service.
 *
 * Uses the keyless public search endpoint (no /v1/ prefix, so no API key is
 * required). Supports both address/postcode (`q`) and coordinate lookups.
 */
class PetrolFinder
{
    private const BASE = 'https://www.petrolfinder.uk';

    /**
     * Search stations by postcode/place name (`$query`) or by coordinates.
     *
     * @return array<int, FuelStationResult>
     */
    public function search(?string $query = null, ?float $latitude = null, ?float $longitude = null, float $radius = 10): array
    {
        $parameters = ['radius' => $radius];

        if ($query !== null) {
            $parameters['q'] = $query;
        } else {
            $parameters['lat'] = $latitude;
            $parameters['lng'] = $longitude;
        }

        $response = Http::get(self::BASE.'/api/search', $parameters);

        if (! $response->successful()) {
            return [];
        }

        return array_map(
            fn (array $station): FuelStationResult => FuelStationResult::fromApi($station),
            $response->json('stations', []),
        );
    }

    /**
     * The single closest station to a coordinate, or null when none are found.
     */
    public function nearest(float $latitude, float $longitude, float $radius = 5): ?FuelStationResult
    {
        $stations = $this->search(latitude: $latitude, longitude: $longitude, radius: $radius);

        usort(
            $stations,
            fn (FuelStationResult $a, FuelStationResult $b): int => ($a->distance ?? INF) <=> ($b->distance ?? INF),
        );

        return $stations[0] ?? null;
    }

    /**
     * Search stations by postcode or place name.
     *
     * @return array<int, FuelStationResult>
     */
    public function searchByAddress(string $query, float $radius = 10): array
    {
        return $this->search(query: $query, radius: $radius);
    }
}
