<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client for the Foursquare/Swarm v2 API (api.foursquare.com/v2).
 *
 * Authenticates with the personal OAuth token from config('services.foursquare')
 * and pages through the account's full check-in history, newest first.
 */
class Foursquare
{
    private const BASE = 'https://api.foursquare.com/v2';

    private const API_VERSION = '20240109';

    private const PER_PAGE = 250;

    /**
     * Yield every check-in item across all pages, newest first.
     *
     * @return iterable<array<string, mixed>>
     *
     * @throws RuntimeException When credentials are missing or a request fails.
     */
    public function checkins(): iterable
    {
        $token = config('services.foursquare.access_token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Foursquare access token is not configured (FOURSQUARE_ACCESS_TOKEN).');
        }

        $offset = 0;

        while (true) {
            $response = Http::get(self::BASE.'/users/self/checkins', [
                'oauth_token' => $token,
                'v' => self::API_VERSION,
                'limit' => self::PER_PAGE,
                'offset' => $offset,
                'sort' => 'newestfirst',
            ]);

            if ($response->failed()) {
                throw new RuntimeException("Foursquare request failed ({$response->status()}): {$response->body()}");
            }

            $items = $response->json('response.checkins.items');

            if (empty($items)) {
                break;
            }

            yield from $items;

            $offset += self::PER_PAGE;
        }
    }
}
