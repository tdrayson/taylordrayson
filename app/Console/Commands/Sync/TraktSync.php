<?php

namespace App\Console\Commands\Sync;

use App\Exceptions\TraktException;
use App\Jobs\EnrichMedia;
use App\Models\Media;
use App\Models\Series;
use App\Services\Trakt;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('trakt:sync {--days=7 : Days back to fetch} {--full : Backfill entire history}')]
#[Description('Sync Trakt watch history to the media timeline')]
class TraktSync extends Command
{
    /**
     * Watch history is captured in UTC; the timeline stores local wall-clock
     * plus a timezone string. Taylor watches from the UK, so every Trakt
     * entry converts to Europe/London.
     */
    private const DISPLAY_TIMEZONE = 'Europe/London';

    /**
     * Self-heal ceiling: if the last synced watch is older than this, cap
     * the incremental window here instead of requesting an unbounded
     * backfill (e.g. a sync that was broken for months).
     */
    private const MAX_CATCHUP_DAYS = 90;

    /**
     * Show summaries fetched this run, keyed by Trakt id. A watch history
     * batch can carry dozens of episodes of the same show, so this avoids
     * hitting `/shows/{id}` once per episode.
     *
     * @var array<int|string, array<string, mixed>|null>
     */
    private array $showSummaries = [];

    /**
     * Series ids that received a new episode this run, keyed by id. Bounds
     * `normalizeEpisodeOrder()` to only the series touched this run instead
     * of a full-table scan: ties only ever arise from newly-imported
     * episodes, so a series untouched this run has no new ties to fix.
     *
     * @var array<int, true>
     */
    private array $affectedSeriesIds = [];

    public function handle(Trakt $trakt): int
    {
        $startAt = $this->resolveStartAt();

        $existing = Media::query()->where('source', 'trakt')->pluck('source_id')->flip();

        // Fail closed: a mid-pagination Trakt failure throws (see `Trakt::historyPage`/
        // `ratingsPage`), and any work already imported before the failure stays
        // (never rolled back), but the command reports failure so a partial sync is
        // never mistaken for a complete one.
        try {
            $filmsCreated = $this->importMovies($trakt, $startAt, $existing);
            $episodesCreated = $this->importEpisodes($trakt, $startAt, $existing);

            $this->normalizeEpisodeOrder();
            $this->syncRatings($trakt);
        } catch (TraktException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Synced {$filmsCreated} film(s) and {$episodesCreated} episode(s).");

        return self::SUCCESS;
    }

    /**
     * Resolve the incremental sync's lower bound, self-healing past a
     * missed or broken schedule: rather than trusting `--days` alone (which
     * would silently skip anything watched between `--days` ago and the
     * last successful sync), the window widens back to the newest synced
     * watch, capped at `MAX_CATCHUP_DAYS` so a very stale sync doesn't
     * trigger an unbounded backfill. `--full` always wins and fetches
     * everything.
     */
    private function resolveStartAt(): ?string
    {
        if ($this->option('full')) {
            return null;
        }

        $default = now()->subDays((int) $this->option('days'));

        /** @var string|null $newest Europe/London wall-clock string, or null with no prior Trakt data. */
        $newest = Media::query()->where('source', 'trakt')->max('occurred_at');

        if ($newest === null) {
            return $default->toIso8601ZuluString();
        }

        $newestUtc = Carbon::parse($newest, self::DISPLAY_TIMEZONE)->utc();
        $floor = now()->subDays(self::MAX_CATCHUP_DAYS);

        $start = $default->min($newestUtc->max($floor));

        return $start->toIso8601ZuluString();
    }

    /**
     * Trakt bulk-marks episodes as watched with the exact same `watched_at`
     * second, so ties between episodes of the same series sort in whatever
     * arbitrary order the database returns them (e.g. S1E7 before S1E6).
     * Nudge each tied episode apart by one second, ordered by
     * (season, episode), so a plain time-sort is correct everywhere.
     *
     * Bounded to `$affectedSeriesIds`: only series that received a new
     * episode this run are re-scanned, rather than every synced episode on
     * every run. Ties only ever arise from newly-imported episodes, so a
     * series untouched this run can't have a new tie to fix, and it's
     * idempotent: once a group has been nudged its timestamps are no longer
     * identical, so a re-run finds no ties there and leaves it untouched.
     */
    private function normalizeEpisodeOrder(): void
    {
        if ($this->affectedSeriesIds === []) {
            return;
        }

        Media::query()
            ->where('source', 'trakt')
            ->where('type', 'episode')
            ->whereNotNull('series_id')
            ->whereIn('series_id', array_keys($this->affectedSeriesIds))
            ->get()
            ->groupBy('series_id')
            ->each(function (Collection $seriesEpisodes): void {
                $seriesEpisodes
                    ->groupBy(fn (Media $media): string => $media->occurred_at->format('Y-m-d H:i:s'))
                    ->each(fn (Collection $group) => $this->nudgeTiedGroup($group));
            });
    }

    /**
     * Reassign `occurred_at` for a group of episodes that all share the same
     * timestamp: the (season, episode) sorted first keeps the shared base
     * time, and each subsequent one gets base + its 0-based rank in seconds.
     * The base is captured once, before any row in the group is reassigned,
     * so every offset in the group is computed from the original shared
     * moment rather than a previously-nudged row.
     *
     * @param  Collection<int, Media>  $group  Episodes sharing one exact `occurred_at`.
     */
    private function nudgeTiedGroup(Collection $group): void
    {
        if ($group->count() < 2) {
            return;
        }

        $base = $group->first()->occurred_at->copy();

        // A tie near end-of-day would otherwise push a later rank's base+rank
        // offset past midnight, moving that episode onto the next calendar
        // day (wrong day bucket, binge-collapse, date-URL). Clamp the base
        // backwards so the whole group's spacing fits inside the same local
        // day; the last-ranked episode still lands on the tied day exactly.
        $count = $group->count();
        $endOfDay = $base->copy()->endOfDay();

        if ($base->copy()->addSeconds($count - 1)->gt($endOfDay)) {
            $base = $endOfDay->copy()->subSeconds($count - 1);
        }

        $group
            ->sort(function (Media $a, Media $b): int {
                $seasonA = $a->meta['season'] ?? 0;
                $seasonB = $b->meta['season'] ?? 0;

                if ($seasonA !== $seasonB) {
                    return $seasonA <=> $seasonB;
                }

                $episodeA = $a->meta['episode'] ?? 0;
                $episodeB = $b->meta['episode'] ?? 0;

                if ($episodeA !== $episodeB) {
                    return $episodeA <=> $episodeB;
                }

                // Stable, deterministic tie-break for genuine duplicates
                // (e.g. a rewatch logged at the identical second) so re-runs
                // don't reshuffle ranks based on incidental query order.
                return $a->id <=> $b->id;
            })
            ->values()
            ->each(function (Media $media, int $rank) use ($base): void {
                $target = $base->copy()->addSeconds($rank);

                if (! $media->occurred_at->equalTo($target)) {
                    $media->occurred_at = $target;
                    $media->save();
                }
            });
    }

    /**
     * Pull the user's personal star ratings (1-10) for movies, shows, and
     * episodes, and apply them onto the matching Media/Series rows. This is
     * Taylor's own opinion, not an aggregate external score, so it's synced
     * separately from watch history and applied by matching each item's
     * `meta.ids.trakt` (or `Series.trakt_id`) against the ratings payload.
     */
    private function syncRatings(Trakt $trakt): void
    {
        // Ratings are "last known wins": a title later un-rated on Trakt keeps
        // its stored value rather than being cleared. This is deliberate. A
        // ratings fetch can transiently fail (returning an empty map), and
        // clearing on absence would then wipe every rating, so we only ever
        // set ratings that are present, never remove them.
        $movieRatings = $this->fetchAllRatingPages($trakt, 'movies');
        $episodeRatings = $this->fetchAllRatingPages($trakt, 'episodes');
        $showRatings = $this->fetchAllRatingPages($trakt, 'shows');

        $this->applyMediaRatings('film', $movieRatings);
        $this->applyMediaRatings('episode', $episodeRatings);
        $this->applySeriesRatings($showRatings);
    }

    /**
     * Apply ratings onto every Media row of the given type whose
     * `meta.ids.trakt` matches an id in the ratings map. Matching happens in
     * PHP against the loaded collection (not a JSON-path `where`) since
     * production isn't SQLite. A film/episode watched (and rated) multiple
     * times has multiple rows sharing the same Trakt id, so every matching
     * row gets the rating.
     *
     * @param  array<int|string, int>  $ratings  Trakt id => rating.
     */
    private function applyMediaRatings(string $mediaType, array $ratings): void
    {
        if ($ratings === []) {
            return;
        }

        Media::query()->where('source', 'trakt')->where('type', $mediaType)->get()
            ->each(function (Media $media) use ($ratings): void {
                $traktId = $media->meta['ids']['trakt'] ?? null;

                if ($traktId === null || ! array_key_exists($traktId, $ratings)) {
                    return;
                }

                $rating = $ratings[$traktId];

                if ($media->rating !== $rating) {
                    $media->rating = $rating;
                    $media->save();
                }
            });
    }

    /**
     * @param  array<int|string, int>  $ratings  Trakt id => rating.
     */
    private function applySeriesRatings(array $ratings): void
    {
        if ($ratings === []) {
            return;
        }

        Series::all()->each(function (Series $series) use ($ratings): void {
            if ($series->trakt_id === null || ! array_key_exists($series->trakt_id, $ratings)) {
                return;
            }

            $rating = $ratings[$series->trakt_id];

            if (($series->meta['rating'] ?? null) === $rating) {
                return;
            }

            $series->meta = array_merge($series->meta ?? [], ['rating' => $rating]);
            $series->save();
        });
    }

    /**
     * Page through a ratings endpoint until an empty batch signals the end,
     * building a flat map of Trakt id => rating. `ratingsPage` fails closed
     * (throws `TraktException` on a failed request), so an empty batch here
     * only ever means "nothing rated in that category", never a swallowed
     * failure.
     *
     * @return array<int|string, int>
     */
    private function fetchAllRatingPages(Trakt $trakt, string $type): array
    {
        $ratings = [];
        $page = 1;

        while (true) {
            $batch = $trakt->ratingsPage($type, $page);

            if ($batch === []) {
                break;
            }

            foreach ($batch as $item) {
                $key = match ($type) {
                    'movies' => $item['movie']['ids']['trakt'] ?? null,
                    'shows' => $item['show']['ids']['trakt'] ?? null,
                    'episodes' => $item['episode']['ids']['trakt'] ?? null,
                    default => null,
                };

                if ($key !== null) {
                    $ratings[$key] = $item['rating'];
                }
            }

            $page++;
        }

        return $ratings;
    }

    /**
     * @param  Collection<string, int>  $existing
     */
    private function importMovies(Trakt $trakt, ?string $startAt, Collection $existing): int
    {
        $created = 0;

        foreach ($this->fetchAllPages($trakt, 'movies', $startAt) as $item) {
            if ($existing->has((string) $item['id'])) {
                continue;
            }

            $this->createFilm($trakt, $item);
            $created++;
        }

        return $created;
    }

    /**
     * @param  Collection<string, int>  $existing
     */
    private function importEpisodes(Trakt $trakt, ?string $startAt, Collection $existing): int
    {
        $created = 0;

        foreach ($this->fetchAllPages($trakt, 'episodes', $startAt) as $item) {
            if ($existing->has((string) $item['id'])) {
                continue;
            }

            $this->createEpisode($trakt, $item);
            $created++;
        }

        return $created;
    }

    /**
     * Page through a history endpoint until an empty batch signals the end.
     * `historyPage` fails closed (throws `TraktException` on a failed
     * request), so an empty batch here only ever means "no more pages",
     * never a swallowed failure.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchAllPages(Trakt $trakt, string $type, ?string $startAt): array
    {
        $items = [];
        $page = 1;

        while (true) {
            $batch = $trakt->historyPage($type, $page, 100, $startAt);

            if ($batch === []) {
                break;
            }

            $items = array_merge($items, $batch);
            $page++;
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function createFilm(Trakt $trakt, array $item): void
    {
        $movie = $item['movie'];

        $media = Media::create([
            'occurred_at' => $this->localWallClock($item['watched_at']),
            'timezone' => self::DISPLAY_TIMEZONE,
            'type' => 'film',
            'title' => $movie['title'],
            'source' => 'trakt',
            'source_id' => (string) $item['id'],
            'meta' => [
                'year' => $movie['year'] ?? null,
                'runtime' => $movie['runtime'] ?? null,
                'ids' => $movie['ids'] ?? [],
            ],
        ]);

        $poster = $movie['images']['poster'][0] ?? null;
        $summary = $poster ? null : $trakt->movie($movie['ids']['trakt'] ?? null);
        $posterUrl = $this->posterUrl($movie, $summary);

        EnrichMedia::dispatch($media, 'movie', $movie['ids']['tmdb'] ?? null, $posterUrl);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function createEpisode(Trakt $trakt, array $item): void
    {
        $show = $item['show'];
        $episode = $item['episode'];
        $summary = $this->showSummary($trakt, $show['ids']['trakt'] ?? null);

        [$series, $wasNew] = $this->resolveSeries($show, $summary);

        $media = Media::create([
            'occurred_at' => $this->localWallClock($item['watched_at']),
            'timezone' => self::DISPLAY_TIMEZONE,
            'type' => 'episode',
            'title' => $episode['title'] ?? "Episode {$episode['number']}",
            'series_id' => $series->id,
            'source' => 'trakt',
            'source_id' => (string) $item['id'],
            'meta' => [
                'season' => $episode['season'],
                'episode' => $episode['number'],
                'show_title' => $show['title'],
                'show_slug' => $show['ids']['slug'] ?? null,
                'runtime' => $episode['runtime'] ?? null,
                'ids' => $episode['ids'] ?? [],
            ],
        ]);

        $this->affectedSeriesIds[$series->id] = true;

        if ($wasNew) {
            $posterUrl = $this->posterUrl($show, $summary);

            EnrichMedia::dispatch($series, 'tv', $show['ids']['tmdb'] ?? null, $posterUrl);
        }
    }

    /**
     * Resolve the episode's series by Trakt id, generating a persisted,
     * service-independent slug on first creation. `meta.aired_episodes` and
     * `meta.seasons` are refreshed from the show summary every run so
     * progress stats stay current as new episodes air.
     *
     * @param  array<string, mixed>  $show
     * @param  array<string, mixed>|null  $summary  The `/shows/{id}` response, if one was needed.
     * @return array{0: Series, 1: bool} The series and whether it was newly created.
     */
    private function resolveSeries(array $show, ?array $summary): array
    {
        $series = Series::firstOrNew(['trakt_id' => $show['ids']['trakt']]);
        $wasNew = ! $series->exists;

        if ($wasNew) {
            $series->fill([
                'slug' => Series::slugFor(
                    $show['title'],
                    $show['year'] ?? null,
                    fn (string $slug): bool => Series::where('slug', $slug)->exists(),
                ),
                'title' => $show['title'],
                'year' => $show['year'] ?? null,
                'overview' => $show['overview'] ?? null,
            ]);
        }

        $airedEpisodes = $show['aired_episodes'] ?? $summary['aired_episodes'] ?? null;
        $seasons = $show['seasons'] ?? $summary['seasons'] ?? null;
        $seasons = is_countable($seasons) ? count($seasons) : $seasons;

        $series->meta = array_merge($series->meta ?? [], array_filter([
            'ids' => $show['ids'] ?? [],
            'aired_episodes' => $airedEpisodes ?? ($series->meta['aired_episodes'] ?? null),
            'seasons' => $seasons ?? ($series->meta['seasons'] ?? null),
        ], fn ($value): bool => $value !== null));

        $series->save();

        return [$series, $wasNew];
    }

    /**
     * Fetch the show summary from `/shows/{id}`, memoising the result for
     * the rest of this run so a batch with dozens of episodes of the same
     * show only triggers one request per show.
     *
     * @return array<string, mixed>|null
     */
    private function showSummary(Trakt $trakt, int|string|null $traktId): ?array
    {
        if ($traktId === null) {
            return null;
        }

        return $this->showSummaries[$traktId] ??= $trakt->show($traktId);
    }

    /**
     * Prefer the poster embedded in the history item (from `extended=full`);
     * fall back to the movie/show summary response when absent.
     *
     * @param  array<string, mixed>  $subject  The `movie` or `show` payload from the history item.
     * @param  array<string, mixed>|null  $summary  The movie/show summary response, if one is available.
     */
    private function posterUrl(array $subject, ?array $summary): ?string
    {
        return $subject['images']['poster'][0] ?? $summary['images']['poster'][0] ?? null;
    }

    /**
     * Convert Trakt's UTC `watched_at` to Europe/London wall-clock digits.
     */
    private function localWallClock(string $watchedAt): string
    {
        return Carbon::parse($watchedAt, 'UTC')->setTimezone(self::DISPLAY_TIMEZONE)->format('Y-m-d H:i:s');
    }
}
