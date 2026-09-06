<?php

namespace App\Services\PocketCasts;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasStringBody;

/**
 * Any authenticated POST endpoint, which is most of the API.
 *
 * The body is encoded here rather than by HasJsonBody so an empty one can be
 * sent as `{}` and not `[]`; some endpoints (e.g. /user/stats/summary) reject
 * the array form with a 500.
 */
class PostRequest extends Request implements HasBody
{
    use HasStringBody;

    protected Method $method = Method::POST;

    /** @param  array<string, mixed>  $payload */
    public function __construct(
        private readonly string $path,
        private readonly array $payload = [],
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->path;
    }

    /** @return array<string, string> */
    protected function defaultHeaders(): array
    {
        return ['Content-Type' => 'application/json'];
    }

    protected function defaultBody(): string
    {
        return json_encode($this->payload === [] ? (object) [] : $this->payload);
    }
}
