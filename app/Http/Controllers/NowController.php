<?php

namespace App\Http\Controllers;

use App\Models\Podcast;
use App\Support\OgMeta;
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
                'thumbnail' => $episode->cover_image ?? $episode->thumbnail,
                'url' => $episode->url(),
            ],
        ];
    }
}
