<?php

namespace App\Services\PetrolPrices;

/**
 * An immutable fuel-station lookup result from the PetrolPrices feed, with casing
 * standardised and the brand canonicalised against the known-brand map.
 */
final readonly class FuelStationResult
{
    public function __construct(
        public string $stationName,
        public ?string $brand,
        public ?string $address,
        public ?string $postcode,
        public ?string $city,
        public ?string $county,
        public ?float $latitude,
        public ?float $longitude,
        public ?float $distanceKm,
    ) {}
}
