<?php

namespace App\Services\Tmdb;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Any read-only TMDB resource by path.
 *
 * One class rather than four: every call is a GET of a path with no body, and
 * the paths differ only by the ids the caller already holds.
 */
class ResourceRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $path) {}

    public function resolveEndpoint(): string
    {
        return $this->path;
    }
}
