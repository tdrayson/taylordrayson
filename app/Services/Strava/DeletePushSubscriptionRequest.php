<?php

namespace App\Services\Strava;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** Unsubscribe, which Strava answers with 204 and an empty body. */
class DeletePushSubscriptionRequest extends Request implements UsesClientCredentials
{
    protected Method $method = Method::DELETE;

    public function __construct(private readonly int $id) {}

    public function resolveEndpoint(): string
    {
        return "/api/v3/push_subscriptions/{$this->id}";
    }

    /** @return array<string, mixed> */
    protected function defaultQuery(): array
    {
        return [
            'client_id' => config('services.strava.client_id'),
            'client_secret' => config('services.strava.client_secret'),
        ];
    }
}
