<?php

namespace App\Console\Commands\Sync;

use App\Actions\FetchStravaActivitySummaries;
use App\Actions\ResolvePhotoCoordinate;
use App\Enums\Source;
use App\Models\Activity;
use App\Services\Strava;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Backfill map coordinates onto Strava photos that are already downloaded,
 * without re-fetching image bytes.
 *
 * Per activity it fetches the (small) photos payload and the GPS stream, then
 * resolves each stored photo's coordinate via {@see ResolvePhotoCoordinate}:
 * Strava's own per-photo `location` first, falling back to stream
 * interpolation. Stored media are matched back to their Strava photo by the
 * unique id embedded in the filename.
 */
#[Signature('strava:photo-locations {--limit=0 : Max activities to locate photos for (0 = all)} {--force : Re-derive coordinates for photos that already have them}')]
#[Description('Backfill map coordinates onto already-downloaded Strava photos')]
class StravaPhotoLocations extends Command
{
    private const RATE_LIMIT = 95;

    private const RATE_WINDOW = 900;

    public function handle(Strava $strava, FetchStravaActivitySummaries $summaries, ResolvePhotoCoordinate $resolveCoordinate): int
    {
        if (! $strava->token()) {
            $this->error('Could not obtain a Strava access token.');

            return self::FAILURE;
        }

        $remote = $summaries();

        if ($remote === null) {
            $this->error('Strava request failed while listing activities.');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');

        $targets = Activity::query()
            ->where('source', Source::Strava->value)
            ->whereNotNull('source_id')
            ->with('media')
            ->get()
            ->filter(fn (Activity $activity): bool => $this->photosToLocate($activity, $force)->isNotEmpty())
            ->filter(fn (Activity $activity): bool => isset($remote[$activity->source_id]))
            ->values();

        if ($targets->isEmpty()) {
            $this->info('No photos to locate.');

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $targets = $targets->take($limit);
        }

        $this->info("Found {$targets->count()} activities with photos to locate.");

        return $this->locatePhotos($strava, $resolveCoordinate, $targets, $remote, $force);
    }

    /**
     * The activity's stored photos that still need a coordinate.
     *
     * @return Collection<int, Media>
     */
    private function photosToLocate(Activity $activity, bool $force): Collection
    {
        return $activity->getMedia('cover')
            ->merge($activity->getMedia('photos'))
            ->filter(fn (Media $media): bool => $media->hasCustomProperty('captured_at'))
            ->filter(fn (Media $media): bool => $force || ! $media->hasCustomProperty('latitude'))
            ->values();
    }

    /**
     * Fetch each activity's photos and stream, then write coordinates onto its
     * stored media, pausing when the Strava rate-limit window fills up.
     *
     * The photos payload carries Strava's own per-photo `location`, which
     * places most photos without needing the stream at all; the stream (and
     * the summary's UTC start_date) are only the fallback for photos Strava
     * did not geotag. An activity with neither a usable location nor a stream
     * simply locates nothing and moves on.
     *
     * @param  Collection<int, Activity>  $targets
     * @param  array<string, array{start_date: ?string, total_photo_count: int}>  $remote
     */
    private function locatePhotos(
        Strava $strava,
        ResolvePhotoCoordinate $resolveCoordinate,
        Collection $targets,
        array $remote,
        bool $force,
    ): int {
        $located = 0;
        $requestsInWindow = 0;
        $windowStart = time();

        foreach ($targets as $activity) {
            if ($requestsInWindow >= self::RATE_LIMIT) {
                $wait = self::RATE_WINDOW - (time() - $windowStart);

                if ($wait > 0) {
                    $this->info("Rate limit reached. Waiting {$wait}s...");
                    sleep($wait);
                }

                $requestsInWindow = 0;
                $windowStart = time();
            }

            $photos = $strava->activityPhotos($activity->source_id);
            $requestsInWindow++;

            if (! is_array($photos) || $photos === []) {
                $this->warn("No photos returned for activity {$activity->source_id}, skipping.");

                continue;
            }

            $byUniqueId = $this->photosByUniqueId($photos);

            // The stream is only the fallback for photos Strava did not geotag,
            // and it is useless without a start to offset against, so it is
            // fetched only when a photo actually lacks its own location and a
            // usable start_date exists. Fully geotagged activities cost no
            // stream request at all.
            $needsStream = $this->photosToLocate($activity, $force)->contains(
                fn (Media $media): bool => ($photo = $byUniqueId[$this->uniqueIdFor($media)] ?? null) !== null
                    && $resolveCoordinate->locationFor($photo) === null,
            );

            $start = $needsStream ? $this->startFor($remote, $activity->source_id) : null;
            $timeStream = [];
            $latlngStream = [];

            if ($start !== null) {
                $streams = $strava->activityStreams($activity->source_id);
                $requestsInWindow++;
                $timeStream = $streams['time']['data'] ?? [];
                $latlngStream = $streams['latlng']['data'] ?? [];
            }

            $count = $this->locatePhotosForActivity(
                $activity,
                $resolveCoordinate,
                $byUniqueId,
                $start,
                $timeStream,
                $latlngStream,
                $force,
            );

            $located += $count;

            $this->info("[{$located}] {$activity->name} - {$count} photo(s) located");
        }

        $this->info("Done. Located {$located} photo(s).");

        return self::SUCCESS;
    }

    /**
     * Index the raw photos payload by Strava's unique_id, so a stored media row
     * can be matched back to the photo it came from.
     *
     * @param  array<int, array<string, mixed>>  $photos
     * @return array<string, array<string, mixed>>
     */
    private function photosByUniqueId(array $photos): array
    {
        $indexed = [];

        foreach ($photos as $photo) {
            $id = $photo['unique_id'] ?? null;

            if (is_string($id) && $id !== '') {
                $indexed[$id] = $photo;
            }
        }

        return $indexed;
    }

    /**
     * The activity's UTC start for the stream fallback, or null when the
     * summary has no usable start_date (location-based photos still place).
     *
     * @param  array<string, array{start_date: ?string, total_photo_count: int}>  $remote
     */
    private function startFor(array $remote, int|string $sourceId): ?CarbonImmutable
    {
        $startDate = $remote[$sourceId]['start_date'] ?? null;

        if (! is_string($startDate) || $startDate === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($startDate);
        } catch (InvalidFormatException) {
            return null;
        }
    }

    /**
     * Write a coordinate onto each of the activity's still-to-locate photos,
     * resolving location-first with a stream fallback.
     *
     * A stored photo with no matching Strava photo (rare - deleted upstream) is
     * skipped on its own; it must never abort the rest of the backfill run.
     *
     * @param  array<string, array<string, mixed>>  $photosByUniqueId
     * @param  array<int, int>  $timeStream
     * @param  array<int, array{0: float, 1: float}>  $latlngStream
     */
    private function locatePhotosForActivity(
        Activity $activity,
        ResolvePhotoCoordinate $resolveCoordinate,
        array $photosByUniqueId,
        ?CarbonImmutable $start,
        array $timeStream,
        array $latlngStream,
        bool $force,
    ): int {
        $count = 0;

        foreach ($this->photosToLocate($activity, $force) as $media) {
            $photo = $photosByUniqueId[$this->uniqueIdFor($media)] ?? null;

            if ($photo === null) {
                continue;
            }

            $coordinate = $resolveCoordinate($photo, $start, $timeStream, $latlngStream);

            if ($coordinate === null) {
                continue;
            }

            $media->setCustomProperty('latitude', $coordinate[0]);
            $media->setCustomProperty('longitude', $coordinate[1]);
            $media->save();

            $count++;
        }

        return $count;
    }

    /**
     * The Strava unique_id a stored photo came from: the explicit custom
     * property when present, else the filename it was stored under.
     */
    private function uniqueIdFor(Media $media): string
    {
        if ($media->hasCustomProperty('strava_photo_id')) {
            return (string) $media->getCustomProperty('strava_photo_id');
        }

        return pathinfo($media->file_name, PATHINFO_FILENAME);
    }
}
