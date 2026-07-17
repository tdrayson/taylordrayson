<?php

namespace App\Services;

use App\Services\PetrolFinder\FuelStationResult;
use App\Services\PetrolFinder\StationNormaliser;
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
     * Canonical brand name + logo keyed by uppercased brand, memoised per instance.
     *
     * @var array<string, array{name: string, logo: ?string}>|null
     */
    private ?array $brandLookup = null;

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
            fn (array $station): FuelStationResult => $this->toResult($station),
            $response->json('stations', []),
        );
    }

    /**
     * Canonical brand name + logo keyed by uppercased brand name, from the
     * brands endpoint. Fetched once per instance; empty on a failed request.
     *
     * @return array<string, array{name: string, logo: ?string}>
     */
    public function brands(): array
    {
        if ($this->brandLookup !== null) {
            return $this->brandLookup;
        }

        $response = Http::get(self::BASE.'/api/brands');
        $lookup = [];

        if ($response->successful()) {
            foreach ($response->json('brands', []) as $brand) {
                $name = $brand['brand'] ?? null;
                if ($name === null) {
                    continue;
                }
                $lookup[mb_strtoupper($name)] = ['name' => $name, 'logo' => $brand['logo'] ?? null];
            }
        }

        return $this->brandLookup = $lookup;
    }

    /**
     * The web domain for a brand name (e.g. "BP" -> "bp.com"), parsed from the
     * brands endpoint's logo URL. Null when the brand is unlisted or the entry
     * has no logo URL.
     */
    public function brandDomain(string $brand): ?string
    {
        $logo = $this->brands()[mb_strtoupper($brand)]['logo'] ?? null;

        if ($logo === null) {
            return null;
        }

        $domain = trim((string) parse_url($logo, PHP_URL_PATH), '/');

        return $domain !== '' ? $domain : null;
    }

    /**
     * Map a raw API station to a result, standardising casing and resolving the
     * brand (and its logo) against the brands endpoint, falling back to a
     * title-cased brand name when the brand is not listed.
     *
     * @param  array<string, mixed>  $station
     */
    private function toResult(array $station): FuelStationResult
    {
        $rawBrand = isset($station['brand']) ? (string) $station['brand'] : null;
        $canonical = $rawBrand !== null ? ($this->brands()[mb_strtoupper($rawBrand)] ?? null) : null;

        return new FuelStationResult(
            stationName: (string) StationNormaliser::name((string) ($station['name'] ?? '')),
            brand: $canonical['name'] ?? ($rawBrand !== null ? StationNormaliser::name($rawBrand) : null),
            brandLogo: $canonical['logo'] ?? null,
            address: isset($station['address']) ? StationNormaliser::address((string) $station['address']) : null,
            postcode: isset($station['postcode']) ? (string) $station['postcode'] : null,
            city: isset($station['city']) ? StationNormaliser::city((string) $station['city']) : null,
            latitude: isset($station['latitude']) ? (float) $station['latitude'] : null,
            longitude: isset($station['longitude']) ? (float) $station['longitude'] : null,
            distance: isset($station['distance']) ? (float) $station['distance'] : null,
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
