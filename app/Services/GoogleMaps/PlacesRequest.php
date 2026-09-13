<?php

namespace App\Services\GoogleMaps;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** A text search of Places. */
class PlacesRequest extends Request
{
    protected Method $method = Method::GET;

    /** @param  array<string, mixed>  $parameters */
    public function __construct(private readonly array $parameters) {}

    public function resolveEndpoint(): string
    {
        return '/place/textsearch/json';
    }

    /** @return array<string, mixed> */
    protected function defaultQuery(): array
    {
        return $this->parameters;
    }
}
