<?php

namespace App\Services\Strava;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** The comments left on an activity, flat: Strava has no threading. */
class CommentsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly int|string $id) {}

    public function resolveEndpoint(): string
    {
        return "/api/v3/activities/{$this->id}/comments";
    }

    /**
     * Strava defaults to 30 with no per_page, silently truncating popular activities.
     *
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        return ['per_page' => 200];
    }
}
