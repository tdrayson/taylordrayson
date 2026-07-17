<?php

namespace App\Services\PetrolFinder;

/**
 * An immutable fuel-station lookup result from the PetrolFinder API.
 */
readonly class FuelStationResult
{
    public function __construct(
        public string $stationName,
        public ?string $brand,
        public ?string $address,
        public ?string $postcode,
        public ?string $city,
        public ?float $latitude,
        public ?float $longitude,
        public ?float $distance,
    ) {}

    /**
     * @param  array<string, mixed>  $station
     */
    public static function fromApi(array $station): self
    {
        return new self(
            stationName: (string) ($station['name'] ?? ''),
            brand: $station['brand'] ?? null,
            address: $station['address'] ?? null,
            postcode: $station['postcode'] ?? null,
            city: $station['city'] ?? null,
            latitude: isset($station['latitude']) ? (float) $station['latitude'] : null,
            longitude: isset($station['longitude']) ? (float) $station['longitude'] : null,
            distance: isset($station['distance']) ? (float) $station['distance'] : null,
        );
    }
}
