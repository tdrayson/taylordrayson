<?php

namespace App\Services;

use App\Services\PetrolPrices\FuelBrands;
use App\Services\PetrolPrices\FuelStationResult;
use App\Services\PetrolPrices\StationNormaliser;
use Illuminate\Support\Facades\Http;

/**
 * Client for the PetrolPrices.com forecourt lookup, which returns GeoJSON
 * features for stations near a coordinate.
 *
 * Needs no credentials. The API works in whole miles, so radii and distances
 * are converted at this boundary and every value leaving this class is in
 * kilometres.
 */
class PetrolPrices
{
    private const BASE = 'https://www.petrolprices.com';

    private const KM_PER_MILE = 1.609344;

    /**
     * Unleaded, the only grade stocked by effectively every forecourt. The feed
     * filters by a single grade per request and has no "any" option, so this is
     * the widest net available; a diesel-only site would be missed.
     */
    private const FUEL_UNLEADED = 2;

    private const BRAND_ANY = 0;

    private const NO_RESULT_LIMIT = 0;

    private const NO_OFFSET = 0;

    private const SORT_BY_DISTANCE = 'distance';

    /**
     * Stations within `$radiusKm` of a coordinate, nearest first.
     *
     * A failed request yields no stations rather than throwing, because the
     * caller writes a reviewable CSV row instead of aborting the run.
     *
     * @return array<int, FuelStationResult>
     */
    public function search(float $latitude, float $longitude, float $radiusKm = 5): array
    {
        // The radius is a whole number of miles, rounded up so a kilometre
        // radius never searches a smaller area than asked for.
        $radiusMiles = max(1, (int) ceil($radiusKm / self::KM_PER_MILE));

        // The feed takes its filters as path segments, in this order.
        $path = implode('/', [
            '/app/geojson',
            self::FUEL_UNLEADED,
            self::BRAND_ANY,
            self::NO_RESULT_LIMIT,
            self::NO_OFFSET,
            self::SORT_BY_DISTANCE,
            $radiusMiles,
        ]);

        $response = Http::get(self::BASE.$path, ['lat' => $latitude, 'lng' => $longitude]);

        if (! $response->successful()) {
            return [];
        }

        return array_map(
            fn (array $feature): FuelStationResult => $this->toResult($feature),
            $response->json('data.features') ?? [],
        );
    }

    /**
     * Map a GeoJSON feature to a result, standardising casing, canonicalising
     * the brand by its id and converting the distance to kilometres.
     *
     * @param  array<string, mixed>  $feature
     */
    private function toResult(array $feature): FuelStationResult
    {
        $properties = $feature['properties'] ?? [];
        $coordinates = $feature['geometry']['coordinates'] ?? [];

        $address = trim(implode(' ', array_filter([
            $properties['address1'] ?? null,
            $properties['address2'] ?? null,
        ])));

        $distanceMiles = $properties['distance_in_miles_from_given_coords'] ?? null;

        return new FuelStationResult(
            stationName: (string) StationNormaliser::tradingName($properties['name'] ?? ''),
            brand: FuelBrands::name($properties['fuel_brand_name'] ?? null),
            address: $address !== '' ? StationNormaliser::address($address) : null,
            postcode: isset($properties['postcode']) ? (string) $properties['postcode'] : null,
            city: StationNormaliser::city($properties['town'] ?? null),
            county: StationNormaliser::city($properties['county'] ?? null) ?: null,
            latitude: isset($coordinates[1]) ? (float) $coordinates[1] : null,
            longitude: isset($coordinates[0]) ? (float) $coordinates[0] : null,
            distanceKm: $distanceMiles !== null ? round((float) $distanceMiles * self::KM_PER_MILE, 3) : null,
        );
    }
}
