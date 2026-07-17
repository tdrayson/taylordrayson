<?php

namespace App\Services\PetrolFinder;

/**
 * An immutable fuel-station lookup result from the PetrolFinder API, with
 * casing standardised and the brand canonicalised against the brands endpoint.
 */
readonly class FuelStationResult
{
    public function __construct(
        public string $stationName,
        public ?string $brand,
        public ?string $brandLogo,
        public ?string $address,
        public ?string $postcode,
        public ?string $city,
        public ?float $latitude,
        public ?float $longitude,
        public ?float $distance,
    ) {}
}
