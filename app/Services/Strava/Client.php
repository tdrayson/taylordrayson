<?php

namespace App\Services\Strava;

use Saloon\Http\Request;

/**
 * Client for the Strava API. The OAuth refresh-token flow, token caching and
 * the re-authenticate-on-401 retry all live on {@see Connector}.
 * Methods return the decoded JSON, or null when the request fails.
 */
class Client
{
    public function __construct(private readonly Connector $connector) {}

    /**
     * The cached access token, refreshing when missing or when forced.
     *
     * @param  bool  $forceRefresh  Bypass the cache and re-authenticate.
     */
    public function token(bool $forceRefresh = false): ?string
    {
        return $this->connector->token($forceRefresh);
    }

    /**
     * A single page of the athlete's activities (summary representation).
     *
     * @param  int|null  $after  Only activities after this Unix timestamp.
     * @return array<int, array<string, mixed>>|null Null on a request failure.
     */
    public function activitiesPage(int $page, int $perPage, ?int $after = null): ?array
    {
        return $this->json(new ActivitiesRequest($page, $perPage, $after));
    }

    /**
     * The detailed representation of a single activity.
     *
     * @return array<string, mixed>|null Null on a request failure.
     */
    public function activity(int|string $id): ?array
    {
        return $this->json(new ActivityRequest($id));
    }

    /**
     * The photos attached to an activity, at the requested size.
     *
     * @return array<int, array<string, mixed>>|null Null on a request failure.
     */
    public function activityPhotos(int|string $id, int $size = 2048): ?array
    {
        return $this->json(new ActivityPhotosRequest($id, $size));
    }

    /**
     * The requested data streams for an activity, keyed by stream type.
     *
     * @param  array<int, string>  $keys
     * @return array<string, array{data: array<int, mixed>}>|null Null on a request failure.
     */
    public function activityStreams(int|string $id, array $keys = ['time', 'latlng']): ?array
    {
        return $this->json(new ActivityStreamsRequest($id, $keys));
    }

    /**
     * Send a request and decode it, treating any failure as no data.
     *
     * @return array<array-key, mixed>|null
     */
    private function json(Request $request): ?array
    {
        // Checked here rather than left to the connector: with no token there is
        // nothing to authenticate with, and an unauthenticated call to Strava is
        // just a slower way of getting null.
        if ($this->connector->token() === null) {
            return null;
        }

        $response = $this->connector->send($request);

        return $response->failed() ? null : $response->json();
    }
}
