<?php

namespace App\Services\Strava;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** The detailed representation of a single activity. */
class ActivityRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly int|string $id) {}

    public function resolveEndpoint(): string
    {
        return "/api/v3/activities/{$this->id}";
    }
}
