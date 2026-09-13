<?php

namespace App\Services\GoogleMaps;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** Named places around a position, ranked by distance. */
class NearbyRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly float $latitude,
        private readonly float $longitude,
        private readonly int $radiusMetres,
        private readonly string $key,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/place/nearbysearch/json';
    }

    /** @return array<string, mixed> */
    protected function defaultQuery(): array
    {
        return [
            'location' => "{$this->latitude},{$this->longitude}",
            'radius' => $this->radiusMetres,
            'key' => $this->key,
        ];
    }
}
