<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * DuckDuckGo's favicon service. Given a domain it returns that site's icon,
 * having already resolved which of the several competing icon declarations a
 * page actually uses.
 *
 * The request goes to DuckDuckGo, never to the linked site, so an author-supplied
 * URL never becomes a request this app makes to an arbitrary host.
 */
class DuckDuckGo
{
    private const BASE = 'https://icons.duckduckgo.com/ip3';

    private const TIMEOUT_SECONDS = 8;

    /**
     * Fetch a domain's favicon as raw image bytes.
     *
     * @return array{status: 'saved'|'unavailable'|'error', body: string|null}
     */
    public function icon(string $domain): array
    {
        $response = Http::timeout(self::TIMEOUT_SECONDS)
            ->get(self::BASE.'/'.$domain.'.ico');

        if ($response->status() === 404) {
            return ['status' => 'unavailable', 'body' => null];
        }

        if (! $response->successful() || ! str_starts_with((string) $response->header('content-type'), 'image/')) {
            return ['status' => 'error', 'body' => null];
        }

        // The service answers for unknown domains with a placeholder rather than
        // a 404, and that placeholder is tiny. Treat a suspiciously small body
        // as "no icon" so those do not get stored as if they were real.
        if (strlen($response->body()) < 100) {
            return ['status' => 'unavailable', 'body' => null];
        }

        return ['status' => 'saved', 'body' => $response->body()];
    }
}
