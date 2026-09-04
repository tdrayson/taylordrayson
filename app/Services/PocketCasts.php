<?php

namespace App\Services;

use App\Services\PocketCasts\DiscoverConnector;
use App\Services\PocketCasts\GetRequest;
use App\Services\PocketCasts\LoginRequest;
use App\Services\PocketCasts\PocketCastsConnector;
use App\Services\PocketCasts\PodcastApiConnector;
use App\Services\PocketCasts\PostRequest;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Saloon\Http\Response;

/**
 * Client for the unofficial Pocket Casts web API. Exchanges the configured
 * credentials for a cached JWT, re-authenticating once on a 401.
 *
 * @phpstan-type PocketCastsResponse array<array-key, mixed>
 */
class PocketCasts
{
    private const TOKEN_CACHE_KEY = 'pocketcasts.token';

    private const TOKEN_TTL_SECONDS = 60 * 60 * 24 * 29;

    public function __construct(
        private readonly PocketCastsConnector $api,
        private readonly PodcastApiConnector $podcastApi,
        private readonly DiscoverConnector $discover,
    ) {
        // The connectors ask for the token; this class owns logging in and
        // caching it, so they get a resolver rather than a value.
        $resolve = fn (bool $forceRefresh): string => $this->token($forceRefresh);

        $this->api->resolvesTokenUsing($resolve);
        $this->podcastApi->resolvesTokenUsing($resolve);
    }

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
        return $this->getPublic('/popular_world.json');
    }

    /**
     * Editorially featured podcasts (public Discover feed).
     *
     * @return PocketCastsResponse
     */
    public function featured(): array
    {
        return $this->getPublic('/featured.json');
    }

    /**
     * Trending podcasts (public Discover feed).
     *
     * @return PocketCastsResponse
     */
    public function trending(): array
    {
        return $this->getPublic('/trending.json');
    }

    /**
     * A podcast's full detail and episode list.
     *
     * @param  string  $uuid  The podcast UUID.
     * @return PocketCastsResponse
     */
    public function podcast(string $uuid): array
    {
        return $this->get("/podcast/full/{$uuid}");
    }

    /**
     * An episode's show notes.
     *
     * @param  string  $episodeUuid  The episode UUID.
     * @return PocketCastsResponse
     */
    public function showNotes(string $episodeUuid): array
    {
        return $this->get("/episode/show_notes/{$episodeUuid}");
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

        $response = $this->api->send(new LoginRequest($email, $password));

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
     * POST an authenticated endpoint.
     *
     * @param  array<string, mixed>  $body
     * @return PocketCastsResponse
     */
    private function post(string $path, array $body = []): array
    {
        return $this->decode($this->api->send(new PostRequest($path, $body)));
    }

    /**
     * GET an authenticated endpoint on the podcast-metadata host.
     *
     * @return PocketCastsResponse
     */
    private function get(string $path): array
    {
        return $this->decode($this->podcastApi->send(new GetRequest($path)));
    }

    /**
     * GET a public Discover feed, which needs no token.
     *
     * @return PocketCastsResponse
     */
    private function getPublic(string $path): array
    {
        return $this->decode($this->discover->send(new GetRequest($path)));
    }

    /**
     * A failure reads the same whichever host it came from.
     *
     * @return PocketCastsResponse
     */
    private function decode(Response $response): array
    {
        if ($response->failed()) {
            throw new RuntimeException("Pocket Casts request failed ({$response->status()}).");
        }

        return $response->json() ?? [];
    }
}
