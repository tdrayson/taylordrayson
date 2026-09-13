<?php

namespace App\Services\Tmdb;

use App\Services\ApiConnector;

/**
 * The TMDB API v3, used for enrichment only.
 *
 * A slower policy than the shared default: TMDB rate-limits in bursts, and the
 * enrichment it feeds is never on a request path a person is waiting on.
 */
class Connector extends ApiConnector
{
    public function resolveBaseUrl(): string
    {
        return 'https://api.themoviedb.org/3';
    }

    /** @return array<string, int> */
    protected function defaultConfig(): array
    {
        return ['connect_timeout' => 10, 'timeout' => 20];
    }

    /** @return array<string, mixed> */
    protected function defaultQuery(): array
    {
        return ['api_key' => config('services.tmdb.key')];
    }
}
