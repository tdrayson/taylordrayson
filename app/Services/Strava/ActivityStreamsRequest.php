<?php

namespace App\Services\Strava;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** The requested data streams for an activity, keyed by stream type. */
class ActivityStreamsRequest extends Request
{
    protected Method $method = Method::GET;

    /** @param  array<int, string>  $keys */
    public function __construct(
        private readonly int|string $id,
        private readonly array $keys,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/api/v3/activities/{$this->id}/streams";
    }

    /** @return array<string, mixed> */
    protected function defaultQuery(): array
    {
        return ['keys' => implode(',', $this->keys), 'key_by_type' => 'true'];
    }
}
