<?php

namespace App\Services;

use App\Services\GoogleFavicons\GoogleFaviconsConnector;
use App\Services\GoogleFavicons\IconRequest;

/**
 * Google's favicon service. Given a domain it returns that site's icon, having
 * already resolved which of the several competing icon declarations a page
 * actually uses.
 *
 * The request goes to Google, never to the linked site, so an author-supplied
 * URL never becomes a request this app makes to an arbitrary host.
 */
class GoogleFavicons
{
    public function __construct(private readonly GoogleFaviconsConnector $connector) {}

    /**
     * Fetch a domain's favicon as raw image bytes.
     *
     * @return array{status: 'saved'|'unavailable'|'error', body: string|null}
     */
    public function icon(string $domain): array
    {
        $response = $this->connector->send(new IconRequest($domain));

        if ($response->status() === 404) {
            return ['status' => 'unavailable', 'body' => null];
        }

        if (! $response->successful() || ! str_starts_with((string) $response->header('content-type'), 'image/')) {
            return ['status' => 'error', 'body' => null];
        }

        // A domain it knows nothing about answers with a generic globe rather
        // than a 404, so an empty body is the only "no icon" this can detect.
        if ($response->body() === '') {
            return ['status' => 'unavailable', 'body' => null];
        }

        return ['status' => 'saved', 'body' => $response->body()];
    }
}
