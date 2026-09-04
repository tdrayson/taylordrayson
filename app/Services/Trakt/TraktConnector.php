<?php

namespace App\Services\Trakt;

use App\Services\ApiConnector;

/**
 * The Trakt API.
 *
 * Every request carries the app's client id; a user token is added per request
 * by the caller, since most reads are public and only history needs one.
 */
class TraktConnector extends ApiConnector
{
    public function resolveBaseUrl(): string
    {
        return 'https://api.trakt.tv';
    }

    /** @return array<string, string> */
    protected function defaultHeaders(): array
    {
        return [
            'trakt-api-version' => '2',
            'trakt-api-key' => (string) config('services.trakt.client_id'),
            'Content-Type' => 'application/json',
        ];
    }

    /** @return array<string, int> */
    protected function defaultConfig(): array
    {
        return ['connect_timeout' => 10, 'timeout' => 20];
    }
}
