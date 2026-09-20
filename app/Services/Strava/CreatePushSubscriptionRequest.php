<?php

namespace App\Services\Strava;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasFormBody;

/**
 * Subscribe to push events. Strava GETs the callback and expects the challenge
 * echoed back before it will accept this, so it only succeeds once the endpoint
 * is deployed and publicly reachable.
 */
class CreatePushSubscriptionRequest extends Request implements HasBody, UsesClientCredentials
{
    use HasFormBody;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $callbackUrl,
        private readonly string $verifyToken,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/api/v3/push_subscriptions';
    }

    /** @return array<string, mixed> */
    protected function defaultBody(): array
    {
        return [
            'client_id' => config('services.strava.client_id'),
            'client_secret' => config('services.strava.client_secret'),
            'callback_url' => $this->callbackUrl,
            'verify_token' => $this->verifyToken,
        ];
    }
}
