<?php

namespace App\Services\PocketCasts;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;
use stdClass;

/** Any authenticated POST endpoint, which is most of the API. */
class PostRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /** @param  array<string, mixed>  $body */
    public function __construct(
        private readonly string $path,
        private readonly array $payload = [],
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->path;
    }

    /** @return array<string, mixed>|stdClass */
    protected function defaultBody(): array|stdClass
    {
        // An empty body must encode as {}, not []; some endpoints (e.g.
        // /user/stats/summary) reject the array form with a 500.
        return $this->payload === [] ? new stdClass : $this->payload;
    }
}
