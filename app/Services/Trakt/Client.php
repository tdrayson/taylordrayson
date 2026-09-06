<?php

namespace App\Services\Trakt;

use App\Exceptions\TraktException;
use App\Services\GetRequest;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Response;

/**
 * Client for the Trakt API. Reads need only the client_id; every `/sync/*`
 * write needs a user OAuth token, minted per-run by the device flow and passed
 * in explicitly rather than stored (only an interactive command writes).
 */
class Client
{
    public function __construct(private readonly Connector $connector) {}

    /** Device-flow poll statuses meaning "keep waiting": 400 pending, 429 slow down. */
    private const DEVICE_PENDING_STATUSES = [400, 429];

    /**
     * Fetch one page of the user's watch history.
     *
     * @param  string  $type  Either "movies" or "episodes".
     * @param  string|null  $startAt  ISO 8601 lower bound, for incremental syncs.
     * @return array<int, array<string, mixed>>
     */
    public function historyPage(string $type, int $page, int $limit = 100, ?string $startAt = null): array
    {
        return $this->getOrFail('/users/'.config('services.trakt.username')."/history/{$type}", array_filter([
            'extended' => 'full',
            'page' => $page,
            'limit' => $limit,
            'start_at' => $startAt,
        ], fn ($value): bool => $value !== null));
    }

    /**
     * Fetch one page of the user's personal star ratings.
     *
     * @param  string  $type  One of "movies", "shows", or "episodes".
     * @return array<int, array<string, mixed>>
     */
    public function ratingsPage(string $type, int $page, int $limit = 100): array
    {
        return $this->getOrFail('/users/'.config('services.trakt.username')."/ratings/{$type}", [
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function show(int|string $traktId): ?array
    {
        return $this->get("/shows/{$traktId}", ['extended' => 'full']);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function movie(int|string $traktId): ?array
    {
        return $this->get("/movies/{$traktId}", ['extended' => 'full']);
    }

    /**
     * Begin the OAuth device flow, returning the code the user approves.
     *
     * @return array{device_code: string, user_code: string, verification_url: string, expires_in: int, interval: int}
     */
    public function deviceCode(): array
    {
        $response = $this->post('/oauth/device/code', [
            'client_id' => config('services.trakt.client_id'),
        ]);

        if ($response->failed()) {
            throw new TraktException("Could not start Trakt device authorisation (status {$response->status()}). Check TRAKT_CLIENT_ID.");
        }

        return $response->json();
    }

    /**
     * Poll until the user approves the device code, then return the access token.
     * Trakt encodes poll state in the HTTP status: 404/409/410/418 are terminal.
     *
     * @param  callable(int $secondsWaited): void|null  $onTick  Fires once per wait.
     */
    public function pollForDeviceToken(string $deviceCode, int $interval = 5, int $expiresIn = 600, ?callable $onTick = null): string
    {
        $secret = config('services.trakt.client_secret');

        if (blank($secret)) {
            throw new TraktException('TRAKT_CLIENT_SECRET is not set. Copy it from your app at https://trakt.tv/oauth/applications.');
        }

        $interval = max($interval, 1);
        $waited = 0;

        while ($waited < $expiresIn) {
            sleep($interval);
            $waited += $interval;

            $response = $this->post('/oauth/device/token', [
                'code' => $deviceCode,
                'client_id' => config('services.trakt.client_id'),
                'client_secret' => $secret,
            ]);

            if ($response->successful()) {
                $token = $response->json('access_token');

                if (blank($token)) {
                    throw new TraktException('Trakt approved the device code but returned no access token.');
                }

                return $token;
            }

            match ($response->status()) {
                404 => throw new TraktException('Trakt did not recognise the device code. Start the command again.'),
                409 => throw new TraktException('That device code was already used. Start the command again.'),
                410 => throw new TraktException('The device code expired before it was approved. Start the command again.'),
                418 => throw new TraktException('Authorisation was denied at trakt.tv. Nothing was changed.'),
                default => in_array($response->status(), self::DEVICE_PENDING_STATUSES, true)
                    ? $onTick && $onTick($waited)
                    : throw new TraktException("Unexpected status {$response->status()} while awaiting Trakt authorisation."),
            };
        }

        throw new TraktException('Timed out waiting for Trakt authorisation.');
    }

    /**
     * Every episode trakt id in the public watch history. Confirms a removal by
     * what remains, since the remove endpoint's own counts are unreliable.
     *
     * @return array<int, int>
     */
    public function episodeTraktIdsInHistory(): array
    {
        $ids = [];

        for ($page = 1; $page <= 200; $page++) {
            $batch = $this->historyPage('episodes', $page);

            if ($batch === []) {
                break;
            }

            foreach ($batch as $item) {
                $traktId = (int) data_get($item, 'episode.ids.trakt');

                if ($traktId !== 0) {
                    $ids[$traktId] = $traktId;
                }
            }
        }

        return array_values($ids);
    }

    /**
     * Every episode trakt id in the authenticated user's history. Ground truth
     * after a removal: the public feed is CDN-cached, `/sync/history` is not.
     *
     * @return array<int, int>
     */
    public function authenticatedEpisodeTraktIdsInHistory(string $accessToken): array
    {
        return $this->distinctFromAuthenticatedHistory($accessToken, 'episodes', 'episode.ids.trakt');
    }

    /**
     * Every history/play id in the authenticated user's episode history, for
     * confirming one play went while the episode's others remain.
     *
     * @return array<int, int>
     */
    public function authenticatedPlayIdsInHistory(string $accessToken): array
    {
        return $this->distinctFromAuthenticatedHistory($accessToken, 'episodes', 'id');
    }

    /**
     * Page the authenticated history and collect the distinct integers found
     * at a dot-path on each item.
     *
     * @return array<int, int>
     */
    private function distinctFromAuthenticatedHistory(string $accessToken, string $type, string $path): array
    {
        $values = [];

        for ($page = 1; $page <= 200; $page++) {
            $response = $this->connector->send(
                (new GetRequest("/sync/history/{$type}", ['page' => $page, 'limit' => 100]))
                    ->authenticate(new TokenAuthenticator($accessToken)),
            );

            if ($response->failed()) {
                throw new TraktException("Reading authenticated Trakt history failed (status {$response->status()}).");
            }

            $batch = $response->json() ?? [];

            if ($batch === []) {
                break;
            }

            foreach ($batch as $item) {
                $value = (int) data_get($item, $path);

                if ($value !== 0) {
                    $values[$value] = $value;
                }
            }
        }

        return array_values($values);
    }

    /**
     * The username a token authenticates as. Guards against authorising a
     * different account than the one being read, which makes removals no-op.
     */
    public function authenticatedUsername(string $accessToken): ?string
    {
        $response = $this->connector->send(
            (new GetRequest('/users/settings'))->authenticate(new TokenAuthenticator($accessToken)),
        );

        return $response->failed() ? null : $response->json('user.username');
    }

    /**
     * Permanently remove specific plays from the watch history. Trakt answers 200
     * even when it deleted nothing, so callers must read the body, not the status.
     *
     * @param  array<int, string|int>  $playIds  History/play ids, NOT movie or
     *                                           episode ids: those delete every play of the title.
     * @return array{deleted: array<string, int>, not_found: array<string, mixed>}
     */
    public function removeHistory(array $playIds, string $accessToken): array
    {
        if ($playIds === []) {
            return ['deleted' => ['movies' => 0, 'episodes' => 0], 'not_found' => []];
        }

        $response = $this->post('/sync/history/remove', [
            'ids' => array_values(array_map('intval', $playIds)),
        ], $accessToken);

        if ($response->failed()) {
            throw new TraktException("Trakt refused the history removal (status {$response->status()}).");
        }

        return $response->json() ?? [];
    }

    /**
     * Remove every play of the given episodes, unlike {@see removeHistory()} which
     * removes one. Also works where the id-based endpoint reports `not_found`.
     *
     * @param  array<int, int|string>  $episodeTraktIds
     * @return array{deleted: array<string, int>, not_found: array{episodes: array<int, mixed>}}
     */
    public function removeEpisodePlays(array $episodeTraktIds, string $accessToken): array
    {
        if ($episodeTraktIds === []) {
            return ['deleted' => ['episodes' => 0], 'not_found' => ['episodes' => []]];
        }

        $response = $this->post('/sync/history/remove', [
            'episodes' => array_values(array_map(
                fn ($id): array => ['ids' => ['trakt' => (int) $id]],
                $episodeTraktIds,
            )),
        ], $accessToken);

        if ($response->failed()) {
            throw new TraktException("Trakt refused the episode removal (status {$response->status()}).");
        }

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<mixed>|null
     */
    private function get(string $path, array $params = []): ?array
    {
        $response = $this->request($path, $params);

        return $response->failed() ? null : $response->json();
    }

    /**
     * GET that throws on failure, unlike {@see get()}: swallowing a failure in a
     * paginated fetch would silently truncate an import.
     *
     * @param  array<string, mixed>  $params
     * @return array<mixed>
     */
    private function getOrFail(string $path, array $params = []): array
    {
        $response = $this->request($path, $params);

        if ($response->failed()) {
            throw new TraktException("Trakt request to {$path} failed with status {$response->status()}.");
        }

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function request(string $path, array $params): Response
    {
        return $this->connector->send(new GetRequest($path, $params));
    }

    /**
     * POST a JSON body, optionally as an authenticated user. No 429 retry: the
     * device-flow poll handles that backoff itself.
     *
     * @param  array<string, mixed>  $body
     */
    private function post(string $path, array $body, ?string $accessToken = null): Response
    {
        $request = new PostRequest($path, $body);

        if ($accessToken !== null) {
            $request->authenticate(new TokenAuthenticator($accessToken));
        }

        return $this->connector->send($request);
    }
}
