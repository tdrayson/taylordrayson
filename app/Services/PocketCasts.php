<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client for the unofficial Pocket Casts web API. Exchanges the configured
 * credentials for a cached JWT, re-authenticating once on a 401.
 *
 * @phpstan-type PocketCastsResponse array<array-key, mixed>
 */
class PocketCasts
{
    private const API_BASE = 'https://api.pocketcasts.com';

    private const PODCAST_API_BASE = 'https://podcast-api.pocketcasts.com';

    private const DISCOVER_BASE = 'https://static.pocketcasts.com/discover/json';

    private const TOKEN_CACHE_KEY = 'pocketcasts.token';

    private const TOKEN_TTL_SECONDS = 60 * 60 * 24 * 29;

    private const USER_AGENT = 'taylordrayson.com (+https://github.com/tdrayson)';

    /**
     * The account's subscribed podcasts.
     *
     * @return PocketCastsResponse
     */
    public function subscriptions(): array
    {
        return $this->post('/user/podcast/list', ['v' => 1]);
    }

    /**
     * New episodes released by subscribed podcasts.
     *
     * @return PocketCastsResponse
     */
    public function newReleases(): array
    {
        return $this->post('/user/new_releases');
    }

    /**
     * @return PocketCastsResponse
     */
    public function inProgress(): array
    {
        return $this->post('/user/in_progress');
    }

    /**
     * @return PocketCastsResponse
     */
    public function starred(): array
    {
        return $this->post('/user/starred');
    }

    /**
     * @return PocketCastsResponse
     */
    public function history(): array
    {
        return $this->post('/user/history');
    }

    /**
     * The lifetime listening-stats summary.
     *
     * @return PocketCastsResponse
     */
    public function stats(): array
    {
        return $this->post('/user/stats/summary');
    }

    /**
     * The Up Next queue.
     *
     * @return PocketCastsResponse
     */
    public function upNext(): array
    {
        return $this->post('/up_next/list', ['version' => 2, 'model' => 'webplayer', 'serverModified' => 0]);
    }

    /**
     * Personalised episode recommendations.
     *
     * @return PocketCastsResponse
     */
    public function recommendedEpisodes(): array
    {
        return $this->post('/discover/recommend_episodes');
    }

    /**
     * Search podcasts and episodes for a term.
     *
     * @param  string  $term  The search query.
     * @return PocketCastsResponse
     */
    public function search(string $term): array
    {
        return $this->post('/discover/search', ['term' => $term]);
    }

    /**
     * The most popular podcasts worldwide (public Discover feed).
     *
     * @return PocketCastsResponse
     */
    public function popular(): array
    {
        return $this->getPublic(self::DISCOVER_BASE.'/popular_world.json');
    }

    /**
     * Editorially featured podcasts (public Discover feed).
     *
     * @return PocketCastsResponse
     */
    public function featured(): array
    {
        return $this->getPublic(self::DISCOVER_BASE.'/featured.json');
    }

    /**
     * Trending podcasts (public Discover feed).
     *
     * @return PocketCastsResponse
     */
    public function trending(): array
    {
        return $this->getPublic(self::DISCOVER_BASE.'/trending.json');
    }

    /**
     * A podcast's full detail and episode list.
     *
     * @param  string  $uuid  The podcast UUID.
     * @return PocketCastsResponse
     */
    public function podcast(string $uuid): array
    {
        return $this->get(self::PODCAST_API_BASE, "/podcast/full/{$uuid}");
    }

    /**
     * An episode's show notes.
     *
     * @param  string  $episodeUuid  The episode UUID.
     * @return PocketCastsResponse
     */
    public function showNotes(string $episodeUuid): array
    {
        return $this->get(self::PODCAST_API_BASE, "/episode/show_notes/{$episodeUuid}");
    }

    /**
     * The cached JWT, authenticating to fetch a fresh one when missing or forced.
     *
     * @param  bool  $forceRefresh  Bypass the cache and re-authenticate.
     */
    public function token(bool $forceRefresh = false): string
    {
        if ($forceRefresh) {
            Cache::forget(self::TOKEN_CACHE_KEY);
        }

        $cached = Cache::get(self::TOKEN_CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $token = $this->login();

        Cache::put(self::TOKEN_CACHE_KEY, $token, self::TOKEN_TTL_SECONDS);

        return $token;
    }

    /**
     * Exchange the configured credentials for a JWT.
     */
    private function login(): string
    {
        $email = config('services.pocketcasts.email');
        $password = config('services.pocketcasts.password');

        if (! is_string($email) || ! is_string($password) || $email === '' || $password === '') {
            throw new RuntimeException('Pocket Casts credentials are not configured (POCKETCASTS_EMAIL / POCKETCASTS_PASSWORD).');
        }

        $response = Http::api()->asJson()
            ->acceptJson()
            ->withHeaders(['User-Agent' => self::USER_AGENT])
            ->post(self::API_BASE.'/user/login', [
                'email' => $email,
                'password' => $password,
                'scope' => 'webplayer',
            ]);

        if ($response->failed()) {
            throw new RuntimeException("Pocket Casts login failed ({$response->status()}).");
        }

        $token = $response->json('token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Pocket Casts login returned no token.');
        }

        return $token;
    }

    /**
     * POST a JSON body to an authenticated api.pocketcasts.com endpoint.
     *
     * @param  string  $path  The endpoint path, with a leading slash.
     * @param  array<string, mixed>  $body  The JSON request body.
     * @return PocketCastsResponse
     */
    private function post(string $path, array $body = []): array
    {
        // Encode an empty body as a JSON object ({}), not an array ([]); some
        // endpoints (e.g. /user/stats/summary) reject the array form with a 500.
        $json = json_encode($body === [] ? (object) [] : $body);

        return $this->send(
            fn (PendingRequest $request): Response => $request->withBody($json, 'application/json')->post(self::API_BASE.$path),
        );
    }

    /**
     * GET an authenticated endpoint on the given host.
     *
     * @param  string  $base  The host base URL.
     * @param  string  $path  The endpoint path, with a leading slash.
     * @return PocketCastsResponse
     */
    private function get(string $base, string $path): array
    {
        return $this->send(fn (PendingRequest $request): Response => $request->get($base.$path));
    }

    /**
     * GET a public (unauthenticated) Discover feed.
     *
     * @param  string  $url  The fully-qualified feed URL.
     * @return PocketCastsResponse
     */
    private function getPublic(string $url): array
    {
        $response = Http::api()->acceptJson()
            ->withHeaders(['User-Agent' => self::USER_AGENT])
            ->get($url);

        if ($response->failed()) {
            throw new RuntimeException("Pocket Casts request failed ({$response->status()}).");
        }

        return $response->json() ?? [];
    }

    /**
     * Run a request with the bearer token attached, re-authenticating once and
     * retrying if the token has expired (a 401 response).
     *
     * @param  callable(PendingRequest): Response  $send  Issues the request on the given client.
     * @return PocketCastsResponse
     */
    private function send(callable $send): array
    {
        $response = $send($this->client($this->token()));

        if ($response->status() === 401) {
            $response = $send($this->client($this->token(true)));
        }

        if ($response->failed()) {
            throw new RuntimeException("Pocket Casts request failed ({$response->status()}).");
        }

        return $response->json() ?? [];
    }

    /**
     * A JSON HTTP client carrying the bearer token and a User-Agent.
     *
     * @param  string  $token  The JWT to send as a bearer token.
     */
    private function client(string $token): PendingRequest
    {
        return Http::api()->acceptJson()
            ->withToken($token)
            ->withHeaders(['User-Agent' => self::USER_AGENT]);
    }
}
