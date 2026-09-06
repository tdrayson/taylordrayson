<?php

namespace App\Services\Rovi;

use Carbon\CarbonImmutable;
use Saloon\Http\Auth\TokenAuthenticator;

/**
 * Read-only client for the Rovi personal API, authenticated with a static
 * Bearer token. Every endpoint returns the same envelope:
 *
 *   { data, paging: { nextCursor, hasMore }, meta: { uid, endpoint, generatedAt } }
 *
 * `data` is a list (foods, activity, steps, weight, water, summaries, routes)
 * or an object (me, streaks, recipes, habits, cycle).
 */
class Client
{
    private const DEFAULT_PAGE_SIZE = 100;

    private const MAX_PAGES = 1000;

    public function __construct(private readonly Connector $connector) {}

    /**
     * Your profile, goals and counters (the `data` object from /v1/me).
     *
     * @return array<string, mixed>|null
     */
    public function profile(): ?array
    {
        return $this->get('/v1/me')['data'] ?? null;
    }

    /**
     * Your food diary: the individual items logged each day, across every page.
     * Each entry carries a dateKey, mealType, quantity and macros. This is the
     * singular /v1/me/food endpoint, distinct from the foods() library below,
     * and it honours from/to date filters.
     *
     * @param  array<string, mixed>  $query  Accepts from/to date filters.
     * @return list<array<string, mixed>>
     */
    public function food(array $query = []): array
    {
        return $this->collect('/v1/me/food', $query);
    }

    /**
     * Your saved food library: the deduplicated catalogue of foods you have
     * logged, with useCount and lastUsed. This is the plural /v1/me/foods
     * endpoint and does not filter by date; use food() for the daily diary.
     *
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function foods(array $query = []): array
    {
        return $this->collect('/v1/me/foods', $query);
    }

    /**
     * Logged workouts/activities across every page.
     *
     * @param  array<string, mixed>  $query  Accepts from/to date filters.
     * @return list<array<string, mixed>>
     */
    public function activity(array $query = []): array
    {
        return $this->collect('/v1/me/activity', $query);
    }

    /**
     * Daily step records (keyed by date), across every page.
     *
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function steps(array $query = []): array
    {
        return $this->collect('/v1/me/steps', $query);
    }

    /**
     * Weight entries (keyed by date), across every page.
     *
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function weight(array $query = []): array
    {
        return $this->collect('/v1/me/weight', $query);
    }

    /**
     * Daily water intake records, across every page.
     *
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function water(array $query = []): array
    {
        return $this->collect('/v1/me/water', $query);
    }

    /**
     * Daily nutrition totals (calories/macros per day), across every page.
     *
     * @param  array<string, mixed>  $query  Accepts from/to date filters.
     * @return list<array<string, mixed>>
     */
    public function summaries(array $query = []): array
    {
        return $this->collect('/v1/me/summaries', $query);
    }

    /**
     * Recorded GPS routes with their coordinate trails, across every page.
     *
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function routes(array $query = []): array
    {
        return $this->collect('/v1/me/routes', $query);
    }

    /**
     * Streak state plus history (the `data` object from /v1/me/streaks).
     *
     * @return array<string, mixed>|null
     */
    public function streaks(): ?array
    {
        return $this->get('/v1/me/streaks')['data'] ?? null;
    }

    /**
     * Your recipes, saved and shared (the `data` object from /v1/me/recipes).
     *
     * @return array<string, mixed>|null
     */
    public function recipes(): ?array
    {
        return $this->get('/v1/me/recipes')['data'] ?? null;
    }

    /**
     * Habit definitions and their daily summaries.
     *
     * @return array<string, mixed>|null
     */
    public function habits(): ?array
    {
        return $this->get('/v1/me/habits')['data'] ?? null;
    }

    /**
     * Menstrual cycle data, when present.
     *
     * @return array<string, mixed>|null
     */
    public function cycle(): ?array
    {
        return $this->get('/v1/me/cycle')['data'] ?? null;
    }

    /**
     * The full account export in a single response.
     *
     * @return array<string, mixed>|null
     */
    public function export(): ?array
    {
        return $this->get('/v1/me/export')['data'] ?? null;
    }

    /**
     * A single request to a Rovi endpoint, returning the decoded envelope
     * (data + paging + meta), or null when the request fails.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    public function get(string $path, array $query = []): ?array
    {
        $key = config('services.rovi.key');

        if (! $key) {
            return null;
        }

        $response = $this->connector->send(
            (new GetRequest($path, $query))->authenticate(new TokenAuthenticator($key)),
        );

        return $response->failed() ? null : $response->json();
    }

    /**
     * Follow the cursor through every page of a list endpoint, returning the
     * merged `data` items. Stops at hasMore=false, a missing cursor, or the page
     * cap. Returns [] on the first failed request.
     *
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function collect(string $path, array $query = []): array
    {
        $query += ['limit' => self::DEFAULT_PAGE_SIZE];
        $items = [];

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $envelope = $this->get($path, $query);

            if ($envelope === null) {
                return $page === 0 ? [] : $items;
            }

            foreach ($envelope['data'] ?? [] as $item) {
                $items[] = $item;
            }

            $cursor = $envelope['paging']['nextCursor'] ?? null;

            if (! ($envelope['paging']['hasMore'] ?? false) || $cursor === null) {
                break;
            }

            $query['cursor'] = $cursor;
        }

        return $items;
    }

    /**
     * Convert a Firestore-style {_seconds, _nanoseconds} stamp to a Carbon
     * instance, or null when the shape is missing or malformed.
     *
     * @param  array<string, mixed>|null  $stamp
     */
    public static function toCarbon(?array $stamp): ?CarbonImmutable
    {
        if (! isset($stamp['_seconds']) || ! is_numeric($stamp['_seconds'])) {
            return null;
        }

        return CarbonImmutable::createFromTimestampUTC((int) $stamp['_seconds']);
    }
}
