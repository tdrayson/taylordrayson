<?php

namespace App\Actions;

use App\Models\Activity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Download an activity's Strava photos as media, the first becoming `cover` and
 * the rest the `photos` gallery. Existing media is cleared first, so re-running is
 * idempotent, and a photo {@see ResolvePhotoCoordinate} cannot place is still
 * stored, just without coordinates.
 */
class SyncStravaPhotos
{
    public function __construct(private ResolvePhotoCoordinate $resolveCoordinate) {}

    /**
     * @param  array<int, array<string, mixed>>  $photos  The raw Strava photos payload.
     * @param  array<string, array{data: array<int, mixed>}>|null  $streams  The raw Strava streams payload.
     * @return int The number of photos stored.
     */
    public function __invoke(
        Activity $activity,
        array $photos,
        ?array $streams = null,
        ?CarbonImmutable $activityStart = null,
    ): int {
        $activity->clearMediaCollection('cover');
        $activity->clearMediaCollection('photos');

        $timeStream = $streams['time']['data'] ?? [];
        $latlngStream = $streams['latlng']['data'] ?? [];

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

            $uniqueId = $photo['unique_id'] ?? (string) Str::uuid();

            $media = $activity->addMediaFromString($response->body())
                ->usingFileName($uniqueId.'.webp');

            $properties = ['strava_photo_id' => $uniqueId];
            $capturedAt = $photo['created_at'] ?? null;

            if (is_string($capturedAt) && $capturedAt !== '') {
                $properties['captured_at'] = $capturedAt;
            }

            $coordinate = ($this->resolveCoordinate)($photo, $activityStart, $timeStream, $latlngStream);

            if ($coordinate !== null) {
                $properties['latitude'] = $coordinate[0];
                $properties['longitude'] = $coordinate[1];
            }

            $media->withCustomProperties($properties);

            $media->toMediaCollection($stored === 0 ? 'cover' : 'photos');

            $stored++;
        }

        return $stored;
    }
}
