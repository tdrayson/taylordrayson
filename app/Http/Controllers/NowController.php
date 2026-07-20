<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Appearance;
use App\Models\Attachment;
use App\Models\Podcast;
use App\Models\Sleep;
use App\Models\TimelineEntry;
use App\Support\GalleryPhotos;
use App\Support\OgMeta;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class NowController extends Controller
{
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
     * @return array{season: int, episode: int, publishedAt: string, duration: int|null, url: string, media: array{id: int, title: string, audioUrl: string|null, videoUrl: string|null, thumbnail: string|null, url: string}}|null
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
                // Square podcast artwork for the audio player's cover slot; the
                // wide cover_image (video still) would be cropped in the square.
                'thumbnail' => $episode->thumbnail ?? $episode->cover_image,
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
     * The most recent real photos (cover + gallery collections) across every
     * entry type, shaped for the photos widget deck. Mirrors the gallery's
     * shaping but samples only the newest attachments to stay cheap.
     *
     * @return array<int, array{src: string, srcset: string|null, url: string, caption: string|null}>
     */
    private function recentPhotos(int $limit = 6): array
    {
        $attachments = Attachment::query()
            ->whereIn('collection_name', ['cover', 'photos'])
            ->whereNot('model_type', Appearance::class)
            ->latest('id')
            ->limit(150)
            ->with(['model' => fn (MorphTo $morphTo) => $morphTo->morphWith([Activity::class => ['media']])])
            ->get();

        return $attachments
            ->filter(fn (Attachment $attachment): bool => $attachment->model !== null)
            ->groupBy(fn (Attachment $attachment): string => $attachment->model_type.':'.$attachment->model_id)
            ->flatMap(function (Collection $group): array {
                $model = $group->first()->model;

                return array_map(
                    fn (array $photo): array => [...$photo, 'sort' => $model->occurred_at],
                    GalleryPhotos::shape($model, $group),
                );
            })
            ->sortByDesc('sort')
            ->take($limit)
            ->map(fn (array $photo): array => [
                'src' => $photo['src'],
                'srcset' => $photo['srcset'] ?? null,
                'url' => $photo['url'],
                'caption' => $photo['caption'] ?? null,
            ])
            ->values()
            ->all();
    }
}
