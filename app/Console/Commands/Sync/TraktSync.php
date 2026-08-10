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

#[Signature('trakt:sync {--days=7 : Days back to fetch} {--full : Backfill entire history} {--skip-ratings : Import watch history only} {--ratings-only : Refresh personal ratings only}')]
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
     * Series ids that received a new episode this run, bounding
     * `normalizeEpisodeOrder()` away from a full-table scan.
     *
     * @var array<int, true>
     */
    private array $affectedSeriesIds = [];

    /**
     * Series ids that already had `EnrichMedia` dispatched, capping it at one
     * per series per run rather than one per episode.
     *
     * @var array<int, true>
     */
    private array $enrichDispatched = [];

    /**
     * Sync watch history, ratings, or both. The two flags are mutually exclusive
     * halves so the every-minute history schedule and the daily ratings one can
     * never race; a plain run does both.
     */
    public function handle(Trakt $trakt): int
    {
        // Fail closed: a mid-pagination Trakt failure throws (see `Trakt::historyPage`/
        // `ratingsPage`), and any work already imported before the failure stays
        // (never rolled back), but the command reports failure so a partial sync is
        // never mistaken for a complete one.
        try {
            if ($this->option('ratings-only')) {
                $this->syncRatings($trakt);
                $this->info('Refreshed Trakt ratings.');

                return self::SUCCESS;
            }

            $startAt = $this->resolveStartAt();

            $existing = Media::query()->where('source', 'trakt')->pluck('source_id')->flip();

            $filmsCreated = $this->importMovies($trakt, $startAt, $existing);
            $episodesCreated = $this->importEpisodes($trakt, $startAt, $existing);

            $this->normalizeEpisodeOrder();

            if (! $this->option('skip-ratings')) {
                $this->syncRatings($trakt);
            }
        } catch (TraktException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Synced {$filmsCreated} film(s) and {$episodesCreated} episode(s).");

        return self::SUCCESS;
    }

    /**
     * Resolve the incremental sync's lower bound. The window widens back to the
     * newest synced watch so a missed schedule self-heals, capped at
     * `MAX_CATCHUP_DAYS`. `--full` fetches everything.
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
     * Nudge episodes sharing an exact `watched_at` one second apart, ordered by
     * (season, episode), so a plain time-sort is correct. Trakt bulk-marks give
     * every episode the same second, which otherwise sorts arbitrarily.
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
     * Reassign `occurred_at` across a tied group: first by (season, episode)
     * keeps the base time, the rest get base + rank in seconds. The base is
     * captured before any reassignment so offsets never compound.
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
                $seasonA = $a->meta->season ?? 0;
                $seasonB = $b->meta->season ?? 0;

                if ($seasonA !== $seasonB) {
                    return $seasonA <=> $seasonB;
                }

                $episodeA = $a->meta->episode ?? 0;
                $episodeB = $b->meta->episode ?? 0;

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
     * Pull personal star ratings (1-10) and apply them onto matching Media/Series
     * rows by `meta.ids.trakt` or `Series.trakt_id`.
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
     * Apply ratings onto every Media row whose `meta.ids.trakt` matches. Matched
     * in PHP rather than by JSON path because production is not SQLite, and every
     * matching row is updated since a rewatch has several.
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
                $traktId = $media->meta->ids->trakt;

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

            // Compared numerically: the stored rating and the one Trakt
            // sends can differ in type without differing in value, and a
            // strict comparison would re-save every show on every run.
            if ($series->meta->rating !== null && (float) $series->meta->rating === (float) $rating) {
                return;
            }

            $series->meta = $series->meta->merge(['rating' => $rating]);
            $series->save();
        });
    }

    /**
     * Page a ratings endpoint into a flat map of Trakt id => rating. `ratingsPage`
     * throws on failure, so an empty batch always means genuinely nothing rated.
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
     * Page a history endpoint to exhaustion. `historyPage` throws on failure, so
     * an empty batch always means no more pages.
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

        // Re-enrich an existing series that's still bare (e.g. a prior
        // enrichment job never ran, or failed after its retries), not just
        // brand new ones. Deduped per run: a batch carrying several episodes
        // of the same bare show must only dispatch once.
        if (! isset($this->enrichDispatched[$series->id]) && ($wasNew || $this->seriesIsBare($series))) {
            $this->enrichDispatched[$series->id] = true;
            $posterUrl = $this->posterUrl($show, $summary);

            EnrichMedia::dispatch($series, 'tv', $show['ids']['tmdb'] ?? null, $posterUrl);
        }
    }

    /**
     * A series is bare when it's missing either its cover artwork or its
     * TMDB enrichment metadata, e.g. because `EnrichMedia` never ran or
     * exhausted its retries after the series was first created.
     */
    private function seriesIsBare(Series $series): bool
    {
        return ! $series->hasMedia('cover') || $series->meta->tmdb->isEmpty();
    }

    /**
     * Resolve the episode's series by Trakt id, minting a persisted slug on first
     * creation and refreshing the aired-episode counts every run.
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

        $series->meta = $series->meta->merge(array_filter([
            'ids' => $show['ids'] ?? [],
            'aired_episodes' => $airedEpisodes ?? $series->meta->airedEpisodes,
            'seasons' => $seasons ?? $series->meta->seasons,
        ], fn ($value): bool => $value !== null));

        $series->save();

        return [$series, $wasNew];
    }

    /**
     * Fetch the show summary from `/shows/{id}`, memoised for the run so a batch
     * of episodes from one show costs a single request.
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
