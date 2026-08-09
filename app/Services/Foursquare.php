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
     * `$afterTimestamp` (unix seconds) narrows the request to check-ins made
     * after that moment, which is what makes a recurring sync cheap: without
     * it every run pages the entire history to find the handful that are new.
     *
     * @return iterable<array<string, mixed>>
     *
     * @throws RuntimeException When credentials are missing or a request fails.
     */
    public function checkins(?int $afterTimestamp = null): iterable
    {
        $token = config('services.foursquare.access_token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Foursquare access token is not configured (FOURSQUARE_ACCESS_TOKEN).');
        }

        $offset = 0;

        while (true) {
            $response = Http::get(self::BASE.'/users/self/checkins', array_filter([
                'oauth_token' => $token,
                'v' => self::API_VERSION,
                'limit' => self::PER_PAGE,
                'offset' => $offset,
                'sort' => 'newestfirst',
                'afterTimestamp' => $afterTimestamp,
            ], fn (mixed $value): bool => $value !== null));

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
