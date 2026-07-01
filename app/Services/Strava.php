<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Client for the Strava API (strava.com/api/v3).
 *
 * Owns the OAuth refresh-token flow: it exchanges the configured refresh token
 * for a short-lived access token, caches it until just before expiry, and
 * transparently re-authenticates once when a request returns 401. Every public
 * method maps to a single endpoint and returns the decoded JSON, or null when
 * the request (or token refresh) fails.
 */
class Strava
{
    private const BASE = 'https://www.strava.com';

    private const TOKEN_CACHE_KEY = 'strava_access_token';

    private const DEFAULT_EXPIRY_SECONDS = 3600;

    /**
     * The cached access token, refreshing via the OAuth refresh-token grant when
     * missing or when forced (after a 401).
     *
     * @param  bool  $forceRefresh  Bypass the cache and re-authenticate.
     */
    public function token(bool $forceRefresh = false): ?string
    {
        if (! $forceRefresh) {
            $cached = cache(self::TOKEN_CACHE_KEY);

            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $response = Http::post(self::BASE.'/oauth/token', [
            'client_id' => config('services.strava.client_id'),
            'client_secret' => config('services.strava.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => config('services.strava.refresh_token'),
        ]);

        if ($response->failed()) {
            return null;
        }

        $data = $response->json();
        $expiresIn = $data['expires_in'] ?? self::DEFAULT_EXPIRY_SECONDS;

        cache([self::TOKEN_CACHE_KEY => $data['access_token']], $expiresIn - 60);

        return $data['access_token'];
    }

    /**
     * A single page of the athlete's activities (summary representation).
     *
     * @param  int|null  $after  Only activities after this Unix timestamp.
     * @return array<int, array<string, mixed>>|null Null on a request failure.
     */
    public function activitiesPage(int $page, int $perPage, ?int $after = null): ?array
    {
        $params = array_filter([
            'after' => $after,
            'per_page' => $perPage,
            'page' => $page,
        ], fn ($value): bool => $value !== null);

        return $this->getJson(self::BASE.'/api/v3/athlete/activities', $params);
    }

    /**
     * The detailed representation of a single activity.
     *
     * @return array<string, mixed>|null Null on a request failure.
     */
    public function activity(int|string $id): ?array
    {
        return $this->getJson(self::BASE."/api/v3/activities/{$id}");
    }

    /**
     * The photos attached to an activity, at the requested size.
     *
     * @return array<int, array<string, mixed>>|null Null on a request failure.
     */
    public function activityPhotos(int|string $id, int $size = 2048): ?array
    {
        return $this->getJson(self::BASE."/api/v3/activities/{$id}/photos", ['size' => $size]);
    }

    /**
     * GET a Strava endpoint with the bearer token attached, re-authenticating
     * once and retrying when the token has expired (a 401 response).
     *
     * @param  array<string, mixed>  $params
     * @return array<array-key, mixed>|null
     */
    private function getJson(string $url, array $params = []): ?array
    {
        $token = $this->token();

        if (! $token) {
            return null;
        }

        $response = Http::withToken($token)->get($url, $params);

        if ($response->status() === 401) {
            $token = $this->token(true);

            if (! $token) {
                return null;
            }

            $response = Http::withToken($token)->get($url, $params);
        }

        return $response->failed() ? null : $response->json();
    }
}
