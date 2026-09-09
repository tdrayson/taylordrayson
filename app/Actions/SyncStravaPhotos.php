<?php

namespace App\Actions;

use App\Models\Activity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Download an activity's Strava photos as media, the first becoming `cover` and
 * the rest the `photos` gallery. A photo {@see ResolvePhotoCoordinate} cannot
 * place is still stored, just without coordinates.
 *
 * Only the photos that are missing are fetched, matched on Strava's own
 * `unique_id`. The whole collection used to be cleared and re-downloaded on
 * every run, and `strava:sync` calls this for existing activities as well as
 * new ones, so a week's photos were re-fetched and re-converted on every
 * scheduled run, minting new media rows and ids each time.
 *
 * Pass `$replace` to get the old behaviour, which `strava:photos --force`
 * wants: a deliberate rebuild that also drops photos deleted on Strava, since
 * an incremental pass has no way to tell those from ones it simply was not told
 * about.
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
        bool $replace = false,
    ): int {
        if ($replace) {
            $activity->clearMediaCollection('cover');
            $activity->clearMediaCollection('photos');
        }

        // Read fresh: the media relation is cached on the model, and clearing
        // the collection used to be what invalidated it.
        $activity->unsetRelation('media');

        $held = $this->storedIds($activity);
        $hasCover = $activity->getMedia('cover')->isNotEmpty();

        $timeStream = $streams['time']['data'] ?? [];
        $latlngStream = $streams['latlng']['data'] ?? [];

        $stored = 0;

        foreach ($photos as $photo) {
            $url = $photo['urls']['2048'] ?? $photo['urls']['600'] ?? null;

            if (! $url) {
                continue;
            }

            $uniqueId = $photo['unique_id'] ?? (string) Str::uuid();

            if (in_array($uniqueId, $held, true)) {
                continue;
            }

            $response = Http::get($url);

            if ($response->failed()) {
                continue;
            }

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

            // The first photo an activity ever gets is its cover; once it has
            // one, later arrivals join the gallery rather than displacing it.
            $media->toMediaCollection($hasCover ? 'photos' : 'cover');

            $held[] = $uniqueId;
            $hasCover = true;
            $stored++;
        }

        // So a caller reading the activity back sees what was just added.
        $activity->unsetRelation('media');

        return $stored;
    }

    /**
     * Strava's own id for every photo already stored, so a second run fetches
     * nothing it already has.
     *
     * @return list<string>
     */
    private function storedIds(Activity $activity): array
    {
        return $activity->getMedia('cover')
            ->merge($activity->getMedia('photos'))
            ->map(fn (Media $media): string => (string) ($media->getCustomProperty('strava_photo_id')
                ?? pathinfo($media->file_name, PATHINFO_FILENAME)))
            ->all();
    }
}
