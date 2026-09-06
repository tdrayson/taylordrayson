<?php

namespace App\Services\Strava;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * Exchange the stored refresh token for an access token.
 *
 * Strava's refresh tokens do not expire, so this is the whole of the OAuth
 * flow the app performs: there is no interactive authorisation to run.
 */
class TokenRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function resolveEndpoint(): string
    {
        return '/oauth/token';
    }

    /** @return array<string, mixed> */
    protected function defaultBody(): array
    {
        return [
            'client_id' => config('services.strava.client_id'),
            'client_secret' => config('services.strava.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => config('services.strava.refresh_token'),
        ];
    }
}
