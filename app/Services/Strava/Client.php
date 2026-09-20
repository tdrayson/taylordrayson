<?php

namespace App\Services\Strava;

use App\Data\StravaSubscriptionResult;

/**
 * Client for the Strava API. The OAuth refresh-token flow, token caching and
 * the re-authenticate-on-401 retry all live on {@see Connector}.
 * Methods return the decoded JSON, or null when the request fails.
 */
class Client
{
    /** Strava's own ceiling for a page of kudos or comments. */
    public const RESPONSES_PER_PAGE = 200;

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
     * The athletes who gave an activity kudos, one page of them.
     *
     * @param  int  $perPage  Strava sends 30 without it, silently truncating a popular activity.
     * @return array<int, array<string, mixed>>|null Null on a request failure.
     */
    public function kudos(int|string $id, int $perPage = self::RESPONSES_PER_PAGE): ?array
    {
        return $this->connector->json("/api/v3/activities/{$id}/kudos", ['per_page' => $perPage]);
    }

    /**
     * The comments left on an activity, one page of them.
     *
     * @param  int  $perPage  Strava sends 30 without it, silently truncating a popular activity.
     * @return array<int, array<string, mixed>>|null Null on a request failure.
     */
    public function comments(int|string $id, int $perPage = self::RESPONSES_PER_PAGE): ?array
    {
        return $this->connector->json("/api/v3/activities/{$id}/comments", ['per_page' => $perPage]);
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

    /**
     * The application's push subscription, or a result carrying Strava's own
     * refusal. Strava allows exactly one per client id, so this is a list of
     * zero or one.
     */
    public function subscription(): StravaSubscriptionResult
    {
        $response = $this->connector->send(new PushSubscriptionsRequest);

        if ($response->failed()) {
            return new StravaSubscriptionResult(false, error: $this->errorFrom($response->json(), $response->status()));
        }

        $subscription = $response->json()[0] ?? null;

        return new StravaSubscriptionResult(
            true,
            isset($subscription['id']) ? (int) $subscription['id'] : null,
            $subscription['callback_url'] ?? null,
        );
    }

    /**
     * Subscribe to push events. Strava verifies the callback before accepting,
     * so a failure here is usually the endpoint rather than the credentials,
     * and its message says which.
     */
    public function createSubscription(string $callbackUrl, string $verifyToken): StravaSubscriptionResult
    {
        $response = $this->connector->send(new CreatePushSubscriptionRequest($callbackUrl, $verifyToken));

        if ($response->failed()) {
            return new StravaSubscriptionResult(false, error: $this->errorFrom($response->json(), $response->status()));
        }

        return new StravaSubscriptionResult(true, (int) $response->json('id'), $callbackUrl);
    }

    public function deleteSubscription(int $id): StravaSubscriptionResult
    {
        $response = $this->connector->send(new DeletePushSubscriptionRequest($id));

        return $response->failed()
            ? new StravaSubscriptionResult(false, $id, error: $this->errorFrom($response->json(), $response->status()))
            : new StravaSubscriptionResult(true, $id);
    }

    /**
     * Strava's refusals arrive as `errors: [{resource, field, code}]` with a
     * `message` above them. Both matter: the message names the problem and the
     * codes say which field caused it.
     *
     * @param  array<array-key, mixed>|null  $body
     */
    private function errorFrom(?array $body, int $status): string
    {
        $message = is_string($body['message'] ?? null) ? $body['message'] : "HTTP {$status}";

        $fields = collect($body['errors'] ?? [])
            ->filter(fn (mixed $error): bool => is_array($error))
            ->map(fn (array $error): string => trim(($error['field'] ?? '').' '.($error['code'] ?? '')))
            ->filter()
            ->implode(', ');

        return $fields === '' ? $message : "{$message} ({$fields})";
    }
}
