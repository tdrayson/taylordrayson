<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Client for the OMDB API (www.omdbapi.com), used for enrichment only
 * (cross-platform ratings, certification, awards). Never authoritative
 * for identity; our own slug + Trakt id remain the source of truth.
 *
 * Authenticates via the `apikey` query param. OMDB signals "not found"
 * with an HTTP 200 and `{"Response":"False"}` rather than a non-2xx
 * status, so that case is treated the same as a failed request.
 */
class Omdb
{
    private const BASE = 'https://www.omdbapi.com/';

    /**
     * @return array<string, mixed>|null
     */
    public function byImdb(string $imdbId): ?array
    {
        return $this->get(['i' => $imdbId]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null
     */
    private function get(array $params): ?array
    {
        $response = $this->request($params);

        if ($response->failed()) {
            return null;
        }

        $body = $response->json();

        if (! is_array($body) || ($body['Response'] ?? null) === 'False') {
            return null;
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function request(array $params): Response
    {
        return Http::get(self::BASE, [
            ...$params,
            'apikey' => config('services.omdb.key'),
        ]);
    }
}
