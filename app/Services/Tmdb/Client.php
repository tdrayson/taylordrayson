<?php

namespace App\Services\Tmdb;


/**
 * Client for the TMDB API v3, used for enrichment only (season/episode structure,
 * artwork). Never authoritative for identity: our own slug and the Trakt id
 * remain the source of truth. Methods return decoded JSON, or null on failure.
 */
class Client
{
    public function __construct(private readonly Connector $connector) {}

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
     * @return array<mixed>|null
     */
    private function get(string $path): ?array
    {
        $response = $this->connector->send(new GetRequest($path));

        return $response->failed() ? null : $response->json();
    }
}
