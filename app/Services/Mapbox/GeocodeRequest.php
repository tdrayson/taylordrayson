<?php

namespace App\Services\Mapbox;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Geocode a search term, or a "longitude,latitude" pair to reverse it. Mapbox
 * takes both through the same endpoint, distinguished only by the query.
 */
class GeocodeRequest extends Request
{
    protected Method $method = Method::GET;

    /** @param  array<string, mixed>  $parameters */
    public function __construct(
        private readonly string $search,
        private readonly array $parameters,
        private readonly string $token,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/'.rawurlencode($this->search).'.json';
    }

    /** @return array<string, mixed> */
    protected function defaultQuery(): array
    {
        return [...$this->parameters, 'access_token' => $this->token];
    }
}
