<?php

namespace App\Console\Commands\Sync;

use App\Jobs\FetchTraktPoster;
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
     * Show summaries fetched this run, keyed by Trakt id. A watch history
     * batch can carry dozens of episodes of the same show, so this avoids
     * hitting `/shows/{id}` once per episode.
     *
     * @var array<int|string, array<string, mixed>|null>
     */
    private array $showSummaries = [];

    public function handle(Trakt $trakt): int
    {
        $startAt = $this->option('full') ? null : now()->subDays((int) $this->option('days'))->toIso8601ZuluString();

        $existing = Media::query()->where('source', 'trakt')->pluck('source_id')->flip();

        $filmsCreated = $this->importMovies($trakt, $startAt, $existing);
        $episodesCreated = $this->importEpisodes($trakt, $startAt, $existing);

        $this->info("Synced {$filmsCreated} film(s) and {$episodesCreated} episode(s).");

        return self::SUCCESS;
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
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchAllPages(Trakt $trakt, string $type, ?string $startAt): array
    {
        $items = [];
        $page = 1;

        while (true) {
            $batch = $trakt->historyPage($type, $page, 100, $startAt);

            if ($batch === null || $batch === []) {
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

        $media = new Media;
        $media->forceFill([
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
        ])->save();

        $poster = $movie['images']['poster'][0] ?? null;
        $summary = $poster ? null : $trakt->movie($movie['ids']['trakt'] ?? null);
        $posterUrl = $this->posterUrl($movie, $summary);

        if ($posterUrl) {
            FetchTraktPoster::dispatch($media, $posterUrl);
        }
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

        $media = new Media;
        $media->forceFill([
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
                'episode_title' => $episode['title'] ?? null,
                'show_title' => $show['title'],
                'show_slug' => $show['ids']['slug'] ?? null,
                'runtime' => $episode['runtime'] ?? null,
                'ids' => $episode['ids'] ?? [],
            ],
        ])->save();

        if ($wasNew) {
            $posterUrl = $this->posterUrl($show, $summary);

            if ($posterUrl) {
                FetchTraktPoster::dispatch($series, $posterUrl);
            }
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
     * Fetch (and memoise for the rest of this run) the show summary, unless
     * the history item's inline `show` object already has everything we
     * need (no episode count, since that's not part of the history payload).
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
