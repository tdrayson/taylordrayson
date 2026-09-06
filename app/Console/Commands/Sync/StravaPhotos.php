<?php

namespace App\Console\Commands\Sync;

use App\Actions\FetchStravaActivitySummaries;
use App\Actions\SyncStravaPhotos;
use App\Enums\Source;
use App\Models\Activity;
use App\Services\Strava\Client;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('strava:photos {--limit=0 : Max activities to fetch photos for (0 = all)} {--force : Re-download photos even for activities already backfilled}')]
#[Description('Backfill Strava photos for existing activities, stored as cover + photo gallery media')]
class StravaPhotos extends Command
{
    private const RATE_LIMIT = 95;

    private const RATE_WINDOW = 900;

    public function handle(Client $strava, SyncStravaPhotos $sync, FetchStravaActivitySummaries $summaries): int
    {
        if (! $strava->token()) {
            $this->error('Could not obtain a Strava access token.');

            return self::FAILURE;
        }

        $targets = $this->resolveTargets($summaries);

        if ($targets === null) {
            return self::FAILURE;
        }

        if ($targets->isEmpty()) {
            $this->info('No activities with photos to backfill.');

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $targets = $targets->take($limit);
        }

        $this->info("Found {$targets->count()} activities with photos to fetch.");

        return $this->fetchPhotos($strava, $sync, $targets);
    }

    /**
     * The local activities that have photos on Strava but are not yet
     * backfilled by the current sync code (unless forced), each paired with
     * the activity's UTC start.
     *
     * @return Collection<int, array{activity: Activity, start: ?CarbonImmutable}>|null Null on a request failure.
     */
    private function resolveTargets(FetchStravaActivitySummaries $summaries): ?Collection
    {
        $remote = $summaries();

        if ($remote === null) {
            $this->error('Strava request failed while listing activities.');

            return null;
        }

        $ours = Activity::query()
            ->where('source', Source::Strava->value)
            ->whereNotNull('source_id')
            ->with('media')
            ->get()
            ->keyBy('source_id');

        $force = (bool) $this->option('force');
        $targets = collect();

        foreach ($remote as $sourceId => $summary) {
            if ($summary['total_photo_count'] < 1) {
                continue;
            }

            $activity = $ours->get($sourceId);

            if (! $activity) {
                continue;
            }

            if (! $force && $this->isMigrated($activity)) {
                continue;
            }

            $targets->push([
                'activity' => $activity,
                'start' => $this->parseStart($summary['start_date'] ?? null),
            ]);
        }

        return $targets;
    }

    /**
     * Whether an activity's photos were backfilled by the current sync code, marked
     * by the `captured_at` custom property only that path writes. Keying the skip
     * off this makes an interrupted backfill resumable.
     */
    private function isMigrated(Activity $activity): bool
    {
        $cover = $activity->getFirstMedia('cover');

        return $cover !== null && $cover->hasCustomProperty('captured_at');
    }

    /**
     * The activity's UTC start, or null when Strava sent no start_date or an
     * unparseable one. A positioning problem must never cost us a photo: the
     * activity's photos still download, they just can't be located.
     */
    private function parseStart(?string $startDate): ?CarbonImmutable
    {
        if (! filled($startDate)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($startDate);
        } catch (InvalidFormatException) {
            $this->warn("Unparseable start_date \"{$startDate}\", storing photos without map positions.");

            return null;
        }
    }

    /**
     * Fetch and store photos for each target activity, pausing when the Strava
     * rate-limit window fills up.
     *
     * @param  Collection<int, array{activity: Activity, start: ?CarbonImmutable}>  $targets
     */
    private function fetchPhotos(Client $strava, SyncStravaPhotos $sync, Collection $targets): int
    {
        $stored = 0;
        $requestsInWindow = 0;
        $windowStart = time();

        foreach ($targets as $target) {
            $activity = $target['activity'];

            if ($requestsInWindow >= self::RATE_LIMIT - 1) {
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

            if ($photos === null) {
                $this->warn("Failed to fetch photos for {$activity->source_id}");

                continue;
            }

            $streams = $strava->activityStreams($activity->source_id);
            $requestsInWindow++;

            if ($streams === null) {
                $this->warn("Failed to fetch streams for {$activity->source_id}, storing photos without map positions.");
            }

            $count = $sync($activity, $photos, $streams, $target['start']);
            $stored += $count;

            $this->info("[{$stored}] {$activity->name} - {$count} photo(s)");
        }

        $this->info("Done. Stored {$stored} photo(s).");

        return self::SUCCESS;
    }
}
