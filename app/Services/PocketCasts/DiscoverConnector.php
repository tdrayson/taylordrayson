<?php

namespace App\Services\PocketCasts;

use App\Services\ApiConnector;

/** The public Discover feeds, which need no token. */
class DiscoverConnector extends ApiConnector
{
    public function resolveBaseUrl(): string
    {
        return 'https://static.pocketcasts.com/discover/json';
    }

    /** @return array<string, string> */
    protected function defaultHeaders(): array
    {
        return ['Accept' => 'application/json', 'User-Agent' => $this->userAgent()];
    }

    private function userAgent(): string
    {
        return 'taylordrayson.com (+'.config('site.social.github').')';
    }
}
