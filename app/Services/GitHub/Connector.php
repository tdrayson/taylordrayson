<?php

namespace App\Services\GitHub;

use App\Services\ApiConnector;

/**
 * The GitHub REST API, used for release metadata.
 *
 * Anonymous requests are allowed at 60 an hour, which is ample for a cached
 * per-repo lookup, so the token is optional and only raises that ceiling.
 */
class Connector extends ApiConnector
{
    public function resolveBaseUrl(): string
    {
        return 'https://api.github.com';
    }

    /** @return array<string, mixed> */
    protected function defaultHeaders(): array
    {
        $token = config('services.github.token');

        return array_filter([
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
            'Authorization' => filled($token) ? 'Bearer '.$token : null,
        ]);
    }
}
