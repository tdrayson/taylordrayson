<?php

namespace App\Services\Trakt;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** Any Trakt read, public or as an authenticated user. */
class GetRequest extends Request
{
    protected Method $method = Method::GET;

    /** @param  array<string, mixed>  $query */
    public function __construct(
        private readonly string $path,
        private readonly array $parameters = [],
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->path;
    }

    /** @return array<string, mixed> */
    protected function defaultQuery(): array
    {
        return $this->parameters;
    }
}
