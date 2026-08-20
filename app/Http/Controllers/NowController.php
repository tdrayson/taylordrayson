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
     * The last seven nights (oldest first) plus the most recent night's stage
     * split, shaped for the sleep widget. Durations are in hours; returns null
     * so the widget falls back to its own placeholder when there is no data.
     *
     * @return array{nights: array<int, float>, stageHours: array{deep: float, core: float, rem: float, awake: float}}|null
     */
    private function recentSleep(): ?array
    {
        $nights = Sleep::query()
            ->latest('occurred_at')
            ->take(7)
            ->get()
            ->reverse()
            ->values();

        if ($nights->isEmpty()) {
            return null;
        }

        $last = $nights->last();

        return [
            'nights' => $nights->map(fn (Sleep $night): float => round($night->duration / 3600, 2))->all(),
            'stageHours' => [
                'deep' => round($last->deep / 3600, 2),
                'core' => round($last->core / 3600, 2),
                'rem' => round($last->rem / 3600, 2),
                'awake' => round($last->awake / 3600, 2),
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
