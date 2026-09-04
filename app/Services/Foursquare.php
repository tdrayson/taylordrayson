<?php

namespace App\Services;

use App\Services\Foursquare\CheckinsRequest;
use App\Services\Foursquare\FoursquareConnector;
use RuntimeException;

/**
 * Client for the Foursquare/Swarm v2 API, paging the account's check-in
 * history newest first.
 */
class Foursquare
{
    private const PER_PAGE = 250;

    public function __construct(private readonly FoursquareConnector $connector) {}

    /**
     * Yield every check-in item across all pages, newest first.
     *
     * @param  int|null  $afterTimestamp  Unix seconds; keeps a recurring sync cheap
     *                                    by not re-paging the whole history.
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

        $paginator = $this->connector->paginate(new CheckinsRequest($token, $afterTimestamp));
        $paginator->setPerPageLimit(self::PER_PAGE);

        foreach ($paginator as $response) {
            if ($response->failed()) {
                throw new RuntimeException("Foursquare request failed ({$response->status()}): {$response->body()}");
            }

            yield from $response->json('response.checkins.items') ?? [];
        }
    }
}
