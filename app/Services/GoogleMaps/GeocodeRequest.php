<?php

namespace App\Services\GoogleMaps;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** The place at a coordinate. */
class GeocodeRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly float $latitude,
        private readonly float $longitude,
        private readonly string $key,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/geocode/json';
    }

    /** @return array<string, mixed> */
    protected function defaultQuery(): array
    {
        return ['latlng' => "{$this->latitude},{$this->longitude}", 'key' => $this->key];
    }
}
