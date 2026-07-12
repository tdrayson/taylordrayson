<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Client for the TMDB API v3 (api.themoviedb.org), used for enrichment only
 * (season/episode structure, poster/backdrop/logo images). Never authoritative
 * for identity; our own slug + Trakt id remain the source of truth.
 *
 * Authenticates via the `api_key` query param. Every public method maps to a
 * single endpoint and returns the decoded JSON array, or null when the
 * request fails.
 */
class Tmdb
{
    private const BASE = 'https://api.themoviedb.org/3';

    /**
     * @return array<string, mixed>|null
     */
    public function tv(int $id): ?array
    {
        return $this->get("/tv/{$id}");
    }

    /**
     * @return array<string, mixed>|null
     */
    public function movie(int $id): ?array
    {
        return $this->get("/movie/{$id}");
    }

    /**
     * @param  string  $kind  Either "tv" or "movie".
     * @return array<string, mixed>|null
     */
    public function images(string $kind, int $id): ?array
    {
        return $this->get("/{$kind}/{$id}/images");
    }

    public function imageUrl(?string $path, string $size): ?string
    {
        if ($path === null) {
            return null;
        }

        return config('services.tmdb.image_base').$size.$path;
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
        return Http::get(self::BASE.$path, [
            ...$params,
            'api_key' => config('services.tmdb.key'),
        ]);
    }
}
