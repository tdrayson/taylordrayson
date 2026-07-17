<?php

namespace App\Console\Commands\Sync;

use App\Actions\FetchStravaActivitySummaries;
use App\Actions\LocatePhotoOnRoute;
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
 * Backfill route coordinates onto Strava photos that are already downloaded.
 *
 * Interpolation happens at sync time, so changing the maths would otherwise mean
 * re-downloading every image. This command re-derives positions from the GPS
 * stream alone and never fetches image bytes.
 */
#[Signature('strava:photo-locations {--limit=0 : Max activities to locate photos for (0 = all)} {--force : Re-derive coordinates for photos that already have them}')]
#[Description('Backfill map coordinates onto already-downloaded Strava photos')]
class StravaPhotoLocations extends Command
{
    private const RATE_LIMIT = 95;

    private const RATE_WINDOW = 900;

    public function handle(Strava $strava, FetchStravaActivitySummaries $summaries, LocatePhotoOnRoute $locate): int
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
            ->where('source', 'strava')
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

        return $this->locatePhotos($strava, $locate, $targets, $remote, $force);
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
     * Fetch each activity's stream and write coordinates onto its photos,
     * pausing when the Strava rate-limit window fills up.
     *
     * An activity whose summary has no start_date, or an unparseable one,
     * cannot be located (there is nothing valid to offset the photo's capture
     * time against), so it is skipped with a warning rather than fabricating a
     * start from CarbonImmutable::parse(null) or letting InvalidFormatException
     * abort the whole run.
     *
     * @param  Collection<int, Activity>  $targets
     * @param  array<string, array{start_date: ?string, total_photo_count: int}>  $remote
     */
    private function locatePhotos(
        Strava $strava,
        LocatePhotoOnRoute $locate,
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

            $startDate = $remote[$activity->source_id]['start_date'] ?? null;

            if (! is_string($startDate) || $startDate === '') {
                $this->warn("No start date for activity {$activity->source_id}, skipping.");

                continue;
            }

            $streams = $strava->activityStreams($activity->source_id);
            $requestsInWindow++;

            $timeStream = $streams['time']['data'] ?? [];
            $latlngStream = $streams['latlng']['data'] ?? [];

            if ($timeStream === [] || $latlngStream === []) {
                $this->warn("No usable stream for activity {$activity->source_id}, skipping.");

                continue;
            }

            try {
                $start = CarbonImmutable::parse($startDate);
            } catch (InvalidFormatException) {
                $this->warn("Unparseable start_date for activity {$activity->source_id}, skipping.");

                continue;
            }

            $count = $this->locatePhotosForActivity($activity, $locate, $start, $timeStream, $latlngStream, $force);

            $located += $count;

            $this->info("[{$located}] {$activity->name} - {$count} photo(s) located");
        }

        $this->info("Done. Located {$located} photo(s).");

        return self::SUCCESS;
    }

    /**
     * Write a coordinate onto each of the activity's still-to-locate photos.
     *
     * A photo whose stored captured_at cannot be parsed is skipped on its own;
     * it must never abort the rest of the backfill run.
     *
     * @param  array<int, int>  $timeStream
     * @param  array<int, array{0: float, 1: float}>  $latlngStream
     */
    private function locatePhotosForActivity(
        Activity $activity,
        LocatePhotoOnRoute $locate,
        CarbonImmutable $start,
        array $timeStream,
        array $latlngStream,
        bool $force,
    ): int {
        $count = 0;

        foreach ($this->photosToLocate($activity, $force) as $media) {
            try {
                $capturedAt = CarbonImmutable::parse($media->getCustomProperty('captured_at'));
            } catch (InvalidFormatException) {
                $this->warn("Unparseable captured_at on media {$media->id}, skipping.");

                continue;
            }

            $coordinate = $locate($capturedAt, $start, $timeStream, $latlngStream);

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
}
