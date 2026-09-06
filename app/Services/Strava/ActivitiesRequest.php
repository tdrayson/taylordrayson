<?php

namespace App\Services\Strava;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** One page of the athlete's activities, in the summary representation. */
class ActivitiesRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly int $page,
        private readonly int $perPage,
        private readonly ?int $after = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/api/v3/athlete/activities';
    }

    /** @return array<string, mixed> */
    protected function defaultQuery(): array
    {
        return array_filter([
            'after' => $this->after,
            'per_page' => $this->perPage,
            'page' => $this->page,
        ], fn (mixed $value): bool => $value !== null);
    }
}
