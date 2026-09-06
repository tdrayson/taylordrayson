<?php

namespace App\Services\Strava;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** The photos attached to an activity, at the requested size. */
class ActivityPhotosRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly int|string $id,
        private readonly int $size,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/api/v3/activities/{$this->id}/photos";
    }

    /** @return array<string, mixed> */
    protected function defaultQuery(): array
    {
        return ['size' => $this->size];
    }
}
