<?php

namespace App\Actions;

use App\Models\Activity;
use Illuminate\Support\Facades\Http;

/**
 * Download an activity's Strava photos and store them as media: the first photo
 * becomes the single `cover`, the rest fill the `photos` gallery. Existing photo
 * media is cleared first so re-running is idempotent.
 */
class SyncStravaPhotos
{
    /**
     * @param  array<int, array<string, mixed>>  $photos  The raw Strava photos payload.
     * @return int The number of photos stored.
     */
    public function __invoke(Activity $activity, array $photos): int
    {
        $activity->clearMediaCollection('cover');
        $activity->clearMediaCollection('photos');

        $stored = 0;

        foreach ($photos as $photo) {
            $url = $photo['urls']['2048'] ?? $photo['urls']['600'] ?? null;

            if (! $url) {
                continue;
            }

            $response = Http::get($url);

            if ($response->failed()) {
                continue;
            }

            $fileName = $stored === 0
                ? 'cover.jpg'
                : 'photo-'.$stored.'.jpg';

            $activity->addMediaFromString($response->body())
                ->usingFileName($fileName)
                ->toMediaCollection($stored === 0 ? 'cover' : 'photos');

            $stored++;
        }

        return $stored;
    }
}
