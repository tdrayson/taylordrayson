<?php

namespace App\Services\Trakt;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * Any Trakt write, including the device-flow token exchanges.
 *
 * No 429 retry: the device-flow poll handles that backoff itself, and a retry
 * here would race it.
 */
class PostRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public ?int $tries = 1;

    /** @param  array<string, mixed>  $body */
    public function __construct(
        private readonly string $path,
        private readonly array $payload,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->path;
    }

    /** @return array<string, mixed> */
    protected function defaultBody(): array
    {
        return $this->payload;
    }
}
