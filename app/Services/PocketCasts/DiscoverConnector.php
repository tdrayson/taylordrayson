<?php

namespace App\Services\PocketCasts;

use App\Services\ApiConnector;

/** The public Discover feeds, which need no token. */
class DiscoverConnector extends ApiConnector
{
    private const USER_AGENT = 'taylordrayson.com (+https://github.com/tdrayson)';

    public function resolveBaseUrl(): string
    {
        return 'https://static.pocketcasts.com/discover/json';
    }

    /** @return array<string, string> */
    protected function defaultHeaders(): array
    {
        return ['Accept' => 'application/json', 'User-Agent' => self::USER_AGENT];
    }
}
