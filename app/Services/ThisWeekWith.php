<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client for the "This Week With" podcast website API
 * (thisweekwith.co.uk/wp-json/podcast/v1).
 *
 * The episodes endpoint is public (no auth) and paginated; this client walks
 * every page and returns the raw episode payloads for the caller to map.
 */
class ThisWeekWith
{
    private const ENDPOINT = 'https://www.thisweekwith.co.uk/wp-json/podcast/v1/episodes';

    /**
     * Every published episode, across all pages.
     *
     * @return list<array<string, mixed>>
     *
     * @throws RuntimeException When a page request fails.
     */
    public function episodes(int $perPage = 50): array
    {
        $episodes = [];
        $page = 1;

        do {
            $response = Http::acceptJson()->get(self::ENDPOINT, [
                'page' => $page,
                'per_page' => $perPage,
            ]);

            if ($response->failed()) {
                throw new RuntimeException("This Week With request failed on page {$page} ({$response->status()}).");
            }

            foreach ($response->json('episodes', []) as $episode) {
                $episodes[] = $episode;
            }

            $totalPages = (int) $response->json('total_pages', 1);
            $page++;
        } while ($page <= $totalPages);

        return $episodes;
    }
}
