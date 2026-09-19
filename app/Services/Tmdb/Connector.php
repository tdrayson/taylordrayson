<?php

namespace App\Services\Tmdb;

use App\Services\ApiConnector;
use RuntimeException;

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
        $key = config('services.tmdb.key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('TMDB key is not configured (TMDB_API_KEY).');
        }

        return ['api_key' => $key];
    }
}
