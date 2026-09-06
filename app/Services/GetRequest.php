<?php

namespace App\Services;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\PaginationPlugin\Contracts\Paginatable;

/**
 * A GET of a path on whichever connector sends it.
 *
 * Most of these APIs expose endpoints that differ only by path, so they share
 * this rather than each declaring the same class. An endpoint with its own
 * query shape gets a named request of its own instead.
 */
class GetRequest extends Request implements Paginatable
{
    protected Method $method = Method::GET;

    /** @param  array<string, mixed>  $parameters */
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
