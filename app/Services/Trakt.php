<?php

namespace App\Services;

use App\Exceptions\TraktException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Client for the Trakt API (api.trakt.tv).
 *
 * Reads authenticate with the client_id alone (the `trakt-api-key` header)
 * against a public profile, so they need no OAuth token. `historyPage`/
 * `ratingsPage` are paginated fetches that drive imports, so they fail
 * closed: a failed response throws `TraktException` rather than being
 * treated as an empty page. `show`/`movie` are best-effort summary lookups
 * and stay null-tolerant.
 *
 * Writes are a separate, narrow path. Trakt gates every `/sync/*` mutation
 * behind a user OAuth token, which the client_id cannot mint, so
 * {@see deviceCode()} and {@see pollForDeviceToken()} implement the device
 * flow and hand the caller a bearer token to pass explicitly into
 * {@see removeHistory()}. The token is deliberately never stored: the only
 * caller is an interactive, occasional cleanup command, and keeping a
 * long-lived write-capable credential on disk to save a 15-second re-auth
 * is a poor trade. Nothing on a schedule needs it.
 */
class Trakt
{
    private const BASE = 'https://api.trakt.tv';

    /**
     * Device-flow poll responses that mean "keep waiting" rather than "fail":
     * 400 is the authorisation-pending heartbeat, 429 is a slow-down request.
     */
    private const DEVICE_PENDING_STATUSES = [400, 429];

    /**
     * Fetch one page of the user's watch history for a given media type.
     *
     * @param  string  $type  Either "movies" or "episodes".
     * @param  string|null  $startAt  ISO 8601 lower bound (for incremental syncs).
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
     * Fetch one page of the user's personal star ratings for a given media type.
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
     * Begin the OAuth device flow.
     *
     * Returns Trakt's device payload: `user_code` (what the human types at
     * `verification_url`), `device_code` (what we poll with), plus the
     * `interval` and `expires_in` bounds that govern polling.
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
     * Poll Trakt until the user approves the device code, then return the
     * access token.
     *
     * Trakt encodes poll state in the HTTP status, so the pending statuses
     * are distinguished from genuine failures: 404/409/410/418 are terminal
     * and each gets a message the operator can act on, rather than being
     * retried until the code expires. `$onTick` fires once per wait so a
     * command can keep the terminal alive.
     *
     * @param  callable(int $secondsWaited): void|null  $onTick
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
     * Every episode trakt id currently present in the user's watch history.
     *
     * Read authoritatively (public history feed) so a removal's effect can be
     * confirmed by what actually remains, rather than trusting the remove
     * endpoint's own `deleted`/`not_found` counts, which have proven
     * unreliable for imported plays.
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
     * Every episode trakt id in the AUTHENTICATED user's history.
     *
     * Reads `/sync/history` with the bearer token rather than the public
     * `/users/{id}/history` feed. The public feed is CDN-cached and can serve
     * a stale copy for a while after a removal; the authenticated sync
     * endpoint is user-scoped and uncached, so it is the ground truth for
     * what a play removal actually did.
     *
     * @return array<int, int>
     */
    public function authenticatedEpisodeTraktIdsInHistory(string $accessToken): array
    {
        return $this->distinctFromAuthenticatedHistory($accessToken, 'episodes', 'episode.ids.trakt');
    }

    /**
     * Every history/play id in the AUTHENTICATED user's episode history.
     *
     * The play-level counterpart of {@see authenticatedEpisodeTraktIdsInHistory()},
     * used to confirm a specific play was removed while other plays of the
     * same episode remain.
     *
     * @return array<int, int>
     */
    public function authenticatedPlayIdsInHistory(string $accessToken): array
    {
        return $this->distinctFromAuthenticatedHistory($accessToken, 'episodes', 'id');
    }

    /**
     * Page the authenticated history for one media type and collect the
     * distinct integer values at a dot-path from each item.
     *
     * @return array<int, int>
     */
    private function distinctFromAuthenticatedHistory(string $accessToken, string $type, string $path): array
    {
        $values = [];

        for ($page = 1; $page <= 200; $page++) {
            $response = $this->pendingRequest($accessToken)
                ->get(self::BASE."/sync/history/{$type}", ['page' => $page, 'limit' => 100]);

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
     * The username the given access token authenticates as.
     *
     * Used to confirm the device flow authorised the same account whose
     * public history is being read: a token for a different account makes
     * every history-id removal come back `not_found`, since the ids belong
     * to someone else.
     */
    public function authenticatedUsername(string $accessToken): ?string
    {
        $response = $this->pendingRequest($accessToken)->get(self::BASE.'/users/settings');

        return $response->failed() ? null : $response->json('user.username');
    }

    /**
     * Permanently remove plays from the user's Trakt watch history.
     *
     * `$playIds` are history/play ids (`media.source_id`), NOT movie or
     * episode ids - passing the latter would delete every play of that
     * title rather than the single spurious one.
     *
     * Trakt answers 200 even when it deleted nothing, reporting counts under
     * `deleted` and unmatched ids under `not_found`, so the caller must read
     * the body rather than trust the status.
     *
     * @param  array<int, string|int>  $playIds
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
     * Remove every history play for the given episodes, by episode trakt id.
     *
     * Where {@see removeHistory()} deletes one specific play by its history
     * id, this deletes all plays of each whole episode - the right tool when
     * an episode should not appear at all, and robust against history ids
     * that the id-based endpoint reports as `not_found`. Episodes Trakt could
     * not match come back under `not_found.episodes`.
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
     * Like {@see get()}, but fails closed: a failed response throws instead
     * of being treated as absent data. Used by the paginated fetches that
     * drive imports, where silently swallowing a failure would truncate a
     * sync without the caller ever knowing.
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
        return $this->pendingRequest()->get(self::BASE.$path, $params);
    }

    /**
     * POST a JSON body, optionally as an authenticated user.
     *
     * The 429 retry that {@see PendingRequest()} applies to reads is dropped
     * here: the device-flow poll treats 429 as its own "slow down" signal and
     * handles the backoff itself, so retrying underneath it would poll
     * harder than Trakt asked.
     *
     * @param  array<string, mixed>  $body
     */
    private function post(string $path, array $body, ?string $accessToken = null): Response
    {
        return $this->pendingRequest($accessToken, retryOnRateLimit: false)
            ->post(self::BASE.$path, $body);
    }

    private function pendingRequest(?string $accessToken = null, bool $retryOnRateLimit = true): PendingRequest
    {
        return Http::withHeaders(array_filter([
            'trakt-api-version' => '2',
            'trakt-api-key' => config('services.trakt.client_id'),
            'Content-Type' => 'application/json',
            'Authorization' => $accessToken !== null ? "Bearer {$accessToken}" : null,
        ]))
            ->connectTimeout(10)
            ->timeout(20)
            ->retry(3, 500, when: fn (\Throwable $e): bool => $e instanceof ConnectionException
                || ($retryOnRateLimit && $e instanceof RequestException && $e->response?->status() === 429), throw: false);
    }
}
