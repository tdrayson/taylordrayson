<?php

namespace App\Http\Controllers;

use App\Models\Podcast;
use App\Models\Sleep;
use App\Models\TimelineEntry;
use App\Queries\PhotoStream;
use App\Support\OgMeta;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class NowController extends Controller
{
    public function __construct(private readonly PhotoStream $photos) {}

    /**
     * Render the live "Now" dashboard of widgets.
     */
    public function index(): Response
    {
        return Inertia::render('Now', [
            'og' => OgMeta::now(),
            'episode' => $this->latestEpisode(),
            'sleep' => $this->recentSleep(),
            'entryCounts' => $this->entryCounts(),
            'photos' => $this->recentPhotos(),
        ]);
    }

    /**
     * The most recent This Week With episode, shaped for the podcast widget.
     *
     * @return array{season: int, episode: int, publishedAt: string, duration: int|null, url: string, media: array{id: int, title: string, audioUrl: string|null, videoUrl: string|null, thumbnail: string|null, audioCover: string|null, url: string}}|null
     */
    private function latestEpisode(): ?array
    {
        $episode = Podcast::query()->latest('occurred_at')->first();

        if ($episode === null) {
            return null;
        }

        return [
            'season' => $episode->season_number,
            'episode' => $episode->episode_number,
            'publishedAt' => $episode->occurred_at->toIso8601String(),
            'duration' => $episode->duration,
            'url' => $episode->url(),
            'media' => [
                'id' => $episode->id,
                'title' => $episode->title,
                'audioUrl' => $episode->audio_url,
                'videoUrl' => $episode->video_url,
                'thumbnail' => $episode->wideArtworkSrc() ?? $episode->squareArtworkSrc(),
                // Square artwork for the bottom audio player; the widget itself
                // shows host headshots, not this image.
                'audioCover' => $episode->squareArtworkSrc() ?? $episode->wideArtworkSrc(),
                'url' => $episode->url(),
            ],
        ];
    }

    /**
     * The last seven calendar nights, oldest first, each carrying its own date
     * so the widget never has to infer one, plus the stage split for whichever
     * night the headline shows.
     *
     * Dated rather than "the last seven records": taking the seven most recent
     * rows and counting back from today labelled them with dates they did not
     * happen on the moment a night was missing.
     *
     * A night is attributed to the morning it ends, so the night just gone is
     * today's row. Missing nights carry a null duration and draw no bar.
     *
     * @return array{nights: array<int, array{date: string, hours: float|null}>, lastNight: array{date: string, hours: float, stageHours: array{deep: float, core: float, rem: float, awake: float}}|null}
     */
    private function recentSleep(): array
    {
        $today = Carbon::today();
        $start = $today->copy()->subDays(6);

        $byDate = Sleep::query()
            ->where('occurred_at', '>=', $start)
            ->get()
            ->keyBy(fn (Sleep $night): string => $night->occurred_at->toDateString());

        $nights = collect(range(0, 6))
            ->map(function (int $offset) use ($start, $byDate): array {
                $date = $start->copy()->addDays($offset)->toDateString();
                $night = $byDate->get($date);

                return [
                    'date' => $date,
                    'hours' => $night ? round($night->duration / 3600, 2) : null,
                ];
            })
            ->all();

        // The night just gone, or the one before it. Beyond that there is
        // nothing recent enough to call last night, and the widget says so.
        $headline = $byDate->get($today->toDateString())
            ?? $byDate->get($today->copy()->subDay()->toDateString());

        return [
            'nights' => $nights,
            'lastNight' => $headline === null ? null : [
                'date' => $headline->occurred_at->toDateString(),
                'hours' => round($headline->duration / 3600, 2),
                'stageHours' => [
                    'deep' => round($headline->deep / 3600, 2),
                    'core' => round($headline->core / 3600, 2),
                    'rem' => round($headline->rem / 3600, 2),
                    'awake' => round($headline->awake / 3600, 2),
                ],
            ],
        ];
    }

    /**
     * Per-day timeline entry counts for the trailing 30 days, oldest first with
     * today last, for the "Last 30 days" widget. Days with no entries are zero.
     *
     * @return array<int, int>
     */
    private function entryCounts(): array
    {
        $today = Carbon::today();
        $start = $today->copy()->subDays(29);

        $countsByDay = TimelineEntry::query()
            ->where('occurred_at', '>=', $start)
            ->get(['occurred_at'])
            ->countBy(fn (TimelineEntry $entry): string => $entry->occurred_at->toDateString());

        return collect(range(0, 29))
            ->map(fn (int $offset): int => $countsByDay->get($start->copy()->addDays($offset)->toDateString(), 0))
            ->all();
    }

    /**
     * The newest real photos for the "Life lately" deck, drawn from the same
     * PhotoStream that backs the /photos gallery so the two never diverge, then
     * trimmed to the deck's shape and the first few cards.
     *
     * @return array<int, array{src: string, srcset: string|null, url: string, caption: string|null}>
     */
    private function recentPhotos(int $limit = 6): array
    {
        return collect(($this->photos)($limit))
            ->map(fn (array $photo): array => [
                'src' => $photo['src'],
                'srcset' => $photo['srcset'] ?? null,
                'url' => $photo['url'],
                'caption' => $photo['caption'] ?? null,
            ])
            ->all();
    }
}
