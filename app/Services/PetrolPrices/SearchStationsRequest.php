<?php

namespace App\Services\PetrolPrices;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * GeoJSON features for stations near a coordinate, nearest first.
 *
 * The feed takes its filters as path segments rather than query parameters,
 * in the order assembled below.
 */
class SearchStationsRequest extends Request
{
    protected Method $method = Method::GET;

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

    public function __construct(
        private readonly float $latitude,
        private readonly float $longitude,
        private readonly int $radiusMiles,
    ) {}

    public function resolveEndpoint(): string
    {
        return implode('/', [
            '/app/geojson',
            self::FUEL_UNLEADED,
            self::BRAND_ANY,
            self::NO_RESULT_LIMIT,
            self::NO_OFFSET,
            self::SORT_BY_DISTANCE,
            $this->radiusMiles,
        ]);
    }

    /** @return array<string, mixed> */
    protected function defaultQuery(): array
    {
        return ['lat' => $this->latitude, 'lng' => $this->longitude];
    }
}
