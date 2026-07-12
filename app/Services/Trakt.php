<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Client for the Trakt API (api.trakt.tv), read-only against a public profile.
 *
 * Authenticates with the client_id alone (the `trakt-api-key` header), so it
 * needs no OAuth token. Every public method maps to a single endpoint and
 * returns the decoded JSON array, or null when the request fails.
 */
class Trakt
{
    private const BASE = 'https://api.trakt.tv';

    /**
     * Fetch one page of the user's watch history for a given media type.
     *
     * @param  string  $type  Either "movies" or "episodes".
     * @param  string|null  $startAt  ISO 8601 lower bound (for incremental syncs).
     * @return array<int, array<string, mixed>>|null
     */
    public function historyPage(string $type, int $page, int $limit = 100, ?string $startAt = null): ?array
    {
        return $this->get('/users/'.config('services.trakt.username')."/history/{$type}", array_filter([
            'extended' => 'full',
            'page' => $page,
            'limit' => $limit,
            'start_at' => $startAt,
        ], fn ($value): bool => $value !== null));
    }

    /**
     * Fetch one page of the user's personal star ratings for a given media type.
     *
     * @param  string  $type  One of "movies", "shows", or "episodes".
     * @return array<int, array<string, mixed>>|null
     */
    public function ratingsPage(string $type, int $page, int $limit = 100): ?array
    {
        return $this->get('/users/'.config('services.trakt.username')."/ratings/{$type}", [
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function show(int|string $traktId): ?array
    {
        return $this->get("/shows/{$traktId}", ['extended' => 'full']);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function movie(int|string $traktId): ?array
    {
        return $this->get("/movies/{$traktId}", ['extended' => 'full']);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<mixed>|null
     */
    private function get(string $path, array $params = []): ?array
    {
        $response = $this->request($path, $params);

        return $response->failed() ? null : $response->json();
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function request(string $path, array $params): Response
    {
        return Http::withHeaders([
            'trakt-api-version' => '2',
            'trakt-api-key' => config('services.trakt.client_id'),
            'Content-Type' => 'application/json',
        ])->get(self::BASE.$path, $params);
    }
}
