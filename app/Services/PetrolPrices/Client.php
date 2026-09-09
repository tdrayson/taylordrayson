<?php

namespace App\Services\PetrolPrices;


/**
 * Client for the PetrolPrices.com forecourt lookup, which returns GeoJSON
 * features for stations near a coordinate.
 *
 * Needs no credentials. The API works in whole miles, so radii and distances
 * are converted at this boundary and every value leaving this class is in
 * kilometres.
 */
class Client
{
    private const KM_PER_MILE = 1.609344;

    public function __construct(private readonly Connector $connector) {}

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

        $response = $this->connector->send(new SearchStationsRequest($latitude, $longitude, $radiusMiles));

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
