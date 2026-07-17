<?php

namespace App\Actions;

use App\Models\Activity;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Download an activity's Strava photos and store them as media: the first photo
 * becomes the single `cover`, the rest fill the `photos` gallery. Existing photo
 * media is cleared first so re-running is idempotent.
 *
 * When a GPS stream and the activity's UTC start are supplied, each photo is
 * located on the route and its coordinate stored as custom properties. A photo
 * that cannot be located is still stored, just without coordinates.
 */
class SyncStravaPhotos
{
    public function __construct(private LocatePhotoOnRoute $locate) {}

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
        $canLocate = $activityStart !== null && $timeStream !== [] && $latlngStream !== [];

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

            $media = $activity->addMediaFromString($response->body())
                ->usingFileName(($photo['unique_id'] ?? Str::uuid()).'.jpg');

            $properties = [];
            $capturedAt = $photo['created_at'] ?? null;

            if (is_string($capturedAt) && $capturedAt !== '') {
                $properties['captured_at'] = $capturedAt;
            }

            $coordinate = $canLocate
                ? $this->coordinateFor($photo, $activityStart, $timeStream, $latlngStream)
                : null;

            if ($coordinate !== null) {
                $properties['latitude'] = $coordinate[0];
                $properties['longitude'] = $coordinate[1];
            }

            if ($properties !== []) {
                $media->withCustomProperties($properties);
            }

            $media->toMediaCollection($stored === 0 ? 'cover' : 'photos');

            $stored++;
        }

        return $stored;
    }

    /**
     * The route coordinate for a single photo, or null when it has no capture
     * time, an unparseable capture time, or falls outside the activity's stream.
     *
     * @param  array<string, mixed>  $photo
     * @param  array<int, int>  $timeStream
     * @param  array<int, array{0: float, 1: float}>  $latlngStream
     * @return array{0: float, 1: float}|null
     */
    private function coordinateFor(
        array $photo,
        CarbonImmutable $activityStart,
        array $timeStream,
        array $latlngStream,
    ): ?array {
        $capturedAt = $photo['created_at'] ?? null;

        if (! is_string($capturedAt) || $capturedAt === '') {
            return null;
        }

        try {
            $capturedTime = CarbonImmutable::parse($capturedAt);
        } catch (InvalidFormatException) {
            return null;
        }

        return ($this->locate)(
            $capturedTime,
            $activityStart,
            $timeStream,
            $latlngStream,
        );
    }
}
