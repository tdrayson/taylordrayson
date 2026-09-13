<?php

namespace App\Console\Commands\Sync;

use App\Exceptions\TraktException;
use App\Jobs\EnrichFromTmdb;
use App\Models\Film;
use App\Models\TvEpisode;
use App\Models\TvShow;
use App\Services\Trakt\Client;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

#[Signature('trakt:sync {--days=7 : Days back to fetch} {--full : Backfill entire history} {--skip-ratings : Import watch history only} {--ratings-only : Refresh personal ratings only}')]
#[Description('Sync Trakt watch history to the films and episodes timeline')]
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
     * TvShow ids that received a new episode this run, bounding
     * `normalizeEpisodeOrder()` away from a full-table scan.
     *
     * @var array<int, true>
     */
    private array $affectedTvShowIds = [];

    /**
     * TvShow ids that already had `EnrichFromTmdb` dispatched, capping it at one
     * per show per run rather than one per episode.
     *
     * @var array<int, true>
     */
    private array $enrichDispatched = [];

    /**
     * Sync watch history, ratings, or both. The two flags are mutually exclusive
     * halves so the every-minute history schedule and the daily ratings one can
     * never race; a plain run does both.
     */
    public function handle(Client $trakt): int
    {
        // Fail closed: a mid-pagination Trakt failure throws (see `Trakt\Client::historyPage`/
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

            // Movie-history and episode-history share one Trakt history id space,
            // but films and episodes now live in separate tables, so dedupe is
            // looked up per table rather than against one shared set.
            $existingFilms = Film::query()->where('source', 'trakt')->pluck('source_id')->flip();
            $existingEpisodes = TvEpisode::query()->where('source', 'trakt')->pluck('source_id')->flip();

            $filmsCreated = $this->importMovies($trakt, $startAt, $existingFilms);
            $episodesCreated = $this->importEpisodes($trakt, $startAt, $existingEpisodes);

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

        /** @var string|null $newestFilm Europe/London wall-clock string, or null with no prior Trakt films. */
        $newestFilm = Film::query()->where('source', 'trakt')->max('occurred_at');
        /** @var string|null $newestEpisode Europe/London wall-clock string, or null with no prior Trakt episodes. */
        $newestEpisode = TvEpisode::query()->where('source', 'trakt')->max('occurred_at');
        $newest = collect([$newestFilm, $newestEpisode])->filter()->max();

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
        if ($this->affectedTvShowIds === []) {
            return;
        }

        TvEpisode::query()
            ->where('source', 'trakt')
            ->whereNotNull('tv_show_id')
            ->whereIn('tv_show_id', array_keys($this->affectedTvShowIds))
            ->get()
            ->groupBy('tv_show_id')
            ->each(function (Collection $showEpisodes): void {
                $showEpisodes
                    ->groupBy(fn (TvEpisode $episode): string => $episode->occurred_at->format('Y-m-d H:i:s'))
                    ->each(fn (Collection $group) => $this->nudgeTiedGroup($group));
            });
    }

    /**
     * Reassign `occurred_at` across a tied group: first by (season, episode)
     * keeps the base time, the rest get base + rank in seconds. The base is
     * captured before any reassignment so offsets never compound.
     *
     * @param  Collection<int, TvEpisode>  $group  Episodes sharing one exact `occurred_at`.
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
            ->sort(function (TvEpisode $a, TvEpisode $b): int {
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
            ->each(function (TvEpisode $episode, int $rank) use ($base): void {
                $target = $base->copy()->addSeconds($rank);

                if (! $episode->occurred_at->equalTo($target)) {
                    $episode->occurred_at = $target;
                    $episode->save();
                }
            });
    }

    /**
     * Pull personal star ratings (1-10) and apply them onto matching Film/TvEpisode/TvShow
     * rows by `meta.ids.trakt` or `TvShow.trakt_id`.
     */
    private function syncRatings(Client $trakt): void
    {
        // Ratings are "last known wins": a title later un-rated on Trakt keeps
        // its stored value rather than being cleared. This is deliberate. A
        // ratings fetch can transiently fail (returning an empty map), and
        // clearing on absence would then wipe every rating, so we only ever
        // set ratings that are present, never remove them.
        $movieRatings = $this->fetchAllRatingPages($trakt, 'movies');
        $episodeRatings = $this->fetchAllRatingPages($trakt, 'episodes');
        $showRatings = $this->fetchAllRatingPages($trakt, 'shows');

        $this->applyRatings(Film::query()->where('source', 'trakt'), $movieRatings);
        $this->applyRatings(TvEpisode::query()->where('source', 'trakt'), $episodeRatings);
        $this->applyTvShowRatings($showRatings);
    }

    /**
     * Apply ratings onto every row a query returns whose `meta.ids.trakt`
     * matches. Matched in PHP rather than by JSON path because production is
     * not SQLite, and every matching row is updated since a rewatch has several.
     *
     * @param  Builder<Film>|Builder<TvEpisode>  $query
     * @param  array<int|string, int>  $ratings  Trakt id => rating.
     */
    private function applyRatings(Builder $query, array $ratings): void
    {
        if ($ratings === []) {
            return;
        }

        $query->get()
            ->each(function (Film|TvEpisode $watched) use ($ratings): void {
                $traktId = $watched->meta->ids->trakt;

                if ($traktId === null || ! array_key_exists($traktId, $ratings)) {
                    return;
                }

                $rating = $ratings[$traktId];

                if ($watched->rating !== $rating) {
                    $watched->rating = $rating;
                    $watched->save();
                }
            });
    }

    /**
     * @param  array<int|string, int>  $ratings  Trakt id => rating.
     */
    private function applyTvShowRatings(array $ratings): void
    {
        if ($ratings === []) {
            return;
        }

        TvShow::all()->each(function (TvShow $tvShow) use ($ratings): void {
            if ($tvShow->trakt_id === null || ! array_key_exists($tvShow->trakt_id, $ratings)) {
                return;
            }

            $rating = $ratings[$tvShow->trakt_id];

            // Compared numerically: the stored rating and the one Trakt
            // sends can differ in type without differing in value, and a
            // strict comparison would re-save every show on every run.
            if ($tvShow->meta->rating !== null && (float) $tvShow->meta->rating === (float) $rating) {
                return;
            }

            $tvShow->meta = $tvShow->meta->merge(['rating' => $rating]);
            $tvShow->save();
        });
    }

    /**
     * Page a ratings endpoint into a flat map of Trakt id => rating. `ratingsPage`
     * throws on failure, so an empty batch always means genuinely nothing rated.
     *
     * @return array<int|string, int>
     */
    private function fetchAllRatingPages(Client $trakt, string $type): array
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
    private function importMovies(Client $trakt, ?string $startAt, Collection $existing): int
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
    private function importEpisodes(Client $trakt, ?string $startAt, Collection $existing): int
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
    private function fetchAllPages(Client $trakt, string $type, ?string $startAt): array
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
    private function createFilm(Client $trakt, array $item): void
    {
        $movie = $item['movie'];

        $film = Film::create([
            'occurred_at' => $this->localWallClock($item['watched_at']),
            'timezone' => self::DISPLAY_TIMEZONE,
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

        EnrichFromTmdb::dispatch($film, 'movie', $movie['ids']['tmdb'] ?? null, $posterUrl, $this->fanartUrl($movie, $summary));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function createEpisode(Client $trakt, array $item): void
    {
        $show = $item['show'];
        $episode = $item['episode'];
        $summary = $this->showSummary($trakt, $show['ids']['trakt'] ?? null);

        [$tvShow, $wasNew] = $this->resolveTvShow($show, $summary);

        TvEpisode::create([
            'occurred_at' => $this->localWallClock($item['watched_at']),
            'timezone' => self::DISPLAY_TIMEZONE,
            'title' => $episode['title'] ?? "Episode {$episode['number']}",
            'tv_show_id' => $tvShow->id,
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

        $this->affectedTvShowIds[$tvShow->id] = true;

        // Re-enrich an existing show that's still bare (e.g. a prior
        // enrichment job never ran, or failed after its retries), not just
        // brand new ones. Deduped per run: a batch carrying several episodes
        // of the same bare show must only dispatch once.
        if (! isset($this->enrichDispatched[$tvShow->id]) && ($wasNew || $this->tvShowIsBare($tvShow))) {
            $this->enrichDispatched[$tvShow->id] = true;
            $posterUrl = $this->posterUrl($show, $summary);

            EnrichFromTmdb::dispatch($tvShow, 'tv', $show['ids']['tmdb'] ?? null, $posterUrl, $this->fanartUrl($show, $summary));
        }
    }

    /**
     * A show is bare when it's missing either its cover artwork or its
     * TMDB enrichment metadata, e.g. because `EnrichFromTmdb` never ran or
     * exhausted its retries after the show was first created.
     */
    private function tvShowIsBare(TvShow $tvShow): bool
    {
        return ! $tvShow->hasMedia('cover') || $tvShow->meta->tmdb->isEmpty();
    }

    /**
     * Resolve the episode's show by Trakt id, minting a persisted slug on first
     * creation and refreshing the aired-episode counts every run.
     *
     * @param  array<string, mixed>  $show
     * @param  array<string, mixed>|null  $summary  The `/shows/{id}` response, if one was needed.
     * @return array{0: TvShow, 1: bool} The show and whether it was newly created.
     */
    private function resolveTvShow(array $show, ?array $summary): array
    {
        $tvShow = TvShow::firstOrNew(['trakt_id' => $show['ids']['trakt']]);
        $wasNew = ! $tvShow->exists;

        if ($wasNew) {
            $tvShow->fill([
                'slug' => TvShow::slugFor(
                    $show['title'],
                    $show['year'] ?? null,
                    fn (string $slug): bool => TvShow::where('slug', $slug)->exists(),
                ),
                'title' => $show['title'],
                'year' => $show['year'] ?? null,
                'overview' => $show['overview'] ?? null,
            ]);
        }

        $airedEpisodes = $show['aired_episodes'] ?? $summary['aired_episodes'] ?? null;
        $seasons = $show['seasons'] ?? $summary['seasons'] ?? null;
        $seasons = is_countable($seasons) ? count($seasons) : $seasons;

        $tvShow->meta = $tvShow->meta->merge(array_filter([
            'ids' => $show['ids'] ?? [],
            'aired_episodes' => $airedEpisodes ?? $tvShow->meta->airedEpisodes,
            'seasons' => $seasons ?? $tvShow->meta->seasons,
        ], fn ($value): bool => $value !== null));

        $tvShow->save();

        return [$tvShow, $wasNew];
    }

    /**
     * Fetch the show summary from `/shows/{id}`, memoised for the run so a batch
     * of episodes from one show costs a single request.
     *
     * @return array<string, mixed>|null
     */
    private function showSummary(Client $trakt, int|string|null $traktId): ?array
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
     * The wide fanart, resolved the same way as the poster.
     *
     * @param  array<string, mixed>  $subject  The `movie` or `show` payload from the history item.
     * @param  array<string, mixed>|null  $summary  The movie/show summary response, if one is available.
     */
    private function fanartUrl(array $subject, ?array $summary): ?string
    {
        return $subject['images']['fanart'][0] ?? $summary['images']['fanart'][0] ?? null;
    }

    /**
     * Convert Trakt's UTC `watched_at` to Europe/London wall-clock digits.
     */
    private function localWallClock(string $watchedAt): string
    {
        return Carbon::parse($watchedAt, 'UTC')->setTimezone(self::DISPLAY_TIMEZONE)->format('Y-m-d H:i:s');
    }
}
