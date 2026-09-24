<?php

namespace App\Services\Strava;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/** The application's push subscription, of which there can only ever be one. */
class PushSubscriptionsRequest extends Request implements UsesClientCredentials
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/api/v3/push_subscriptions';
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
