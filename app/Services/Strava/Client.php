<?php

namespace App\Services\Strava;

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
        return $this->connector->json('/api/v3/athlete/activities', array_filter([
            'after' => $after,
            'per_page' => $perPage,
            'page' => $page,
        ], fn (mixed $value): bool => $value !== null));
    }

    /**
     * The detailed representation of a single activity.
     *
     * @return array<string, mixed>|null Null on a request failure.
     */
    public function activity(int|string $id): ?array
    {
        return $this->connector->json("/api/v3/activities/{$id}");
    }

    /**
     * The photos attached to an activity, at the requested size.
     *
     * @return array<int, array<string, mixed>>|null Null on a request failure.
     */
    public function activityPhotos(int|string $id, int $size = 2048): ?array
    {
        return $this->connector->json("/api/v3/activities/{$id}/photos", ['size' => $size]);
    }

    /**
     * The athletes who gave an activity kudos.
     *
     * @return array<int, array<string, mixed>>|null Null on a request failure.
     */
    public function kudos(int|string $id): ?array
    {
        return $this->json(new KudosRequest($id));
    }

    /**
     * The comments left on an activity.
     *
     * @return array<int, array<string, mixed>>|null Null on a request failure.
     */
    public function comments(int|string $id): ?array
    {
        return $this->json(new CommentsRequest($id));
    }

    /**
     * The requested data streams for an activity, keyed by stream type.
     *
     * @param  array<int, string>  $keys
     * @return array<string, array{data: array<int, mixed>}>|null Null on a request failure.
     */
    public function activityStreams(int|string $id, array $keys = ['time', 'latlng']): ?array
    {
        return $this->connector->json("/api/v3/activities/{$id}/streams", [
            'keys' => implode(',', $keys),
            'key_by_type' => 'true',
        ]);
    }
}
