<?php

namespace App\Services\ThisWeekWith;

use App\Services\GetRequest;
use RuntimeException;

/**
 * Client for the "This Week With" podcast website API
 * (thisweekwith.co.uk/wp-json/podcast/v1).
 *
 * The episodes endpoint is public (no auth) and paginated; this client walks
 * every page and returns the raw episode payloads for the caller to map.
 */
class Client
{
    public function __construct(private readonly Connector $connector) {}

    /**
     * Yield every published episode, newest first, fetching each page only as
     * the caller reaches it.
     *
     * Lazy rather than eager so an incremental sync can stop once it reaches
     * episodes it already has: the endpoint returns newest first, so breaking
     * out of the loop means the remaining pages are never requested at all.
     *
     * @return iterable<array<string, mixed>>
     *
     * @throws RuntimeException When a page request fails.
     */
    public function episodes(int $perPage = 50): iterable
    {
        $paginator = $this->connector->paginate(new GetRequest('/episodes'));
        $paginator->setPerPageLimit($perPage);

        // The paginator yields responses, not items, so a failed page can still
        // be turned into the same exception the callers already expect.
        foreach ($paginator as $response) {
            if ($response->failed()) {
                throw new RuntimeException("This Week With request failed on page {$paginator->getCurrentPage()} ({$response->status()}).");
            }

            yield from $response->json('episodes', []);
        }
    }
}
