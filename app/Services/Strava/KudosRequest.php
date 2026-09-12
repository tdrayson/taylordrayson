<?php

namespace App\Services\Strava;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** The athletes who gave an activity kudos. Names only: no id, no photo. */
class KudosRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly int|string $id) {}

    public function resolveEndpoint(): string
    {
        return "/api/v3/activities/{$this->id}/kudos";
    }
}
