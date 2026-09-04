<?php

namespace App\Services\PocketCasts;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** Any GET endpoint, authenticated or public depending on its connector. */
class GetRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $path) {}

    public function resolveEndpoint(): string
    {
        return $this->path;
    }
}
