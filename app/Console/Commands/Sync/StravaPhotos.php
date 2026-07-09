<?php

namespace App\Console\Commands\Sync;

use App\Actions\SyncStravaPhotos;
use App\Models\Activity;
use App\Services\Strava;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('strava:photos {--limit=0 : Max activities to fetch photos for (0 = all)} {--force : Re-download photos for activities that already have them}')]
#[Description('Backfill Strava photos for existing activities, stored as cover + photo gallery media')]
class StravaPhotos extends Command
{
    private const PER_PAGE = 200;

    private const RATE_LIMIT = 95;

    private const RATE_WINDOW = 900;

    public function handle(Strava $strava, SyncStravaPhotos $sync): int
    {
        if (! $strava->token()) {
            $this->error('Could not obtain a Strava access token.');

            return self::FAILURE;
        }

        $targets = $this->resolveTargets($strava);

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
     * Page the Strava activity list and resolve the local activities that have
     * photos on Strava but (unless forced) no stored photo media yet.
     *
     * @return Collection<int, Activity>|null Null on a request failure.
     */
    private function resolveTargets(Strava $strava): ?Collection
    {
        $ours = Activity::query()
            ->where('source', 'strava')
            ->whereNotNull('source_id')
            ->with('media')
            ->get()
            ->keyBy('source_id');

        $force = (bool) $this->option('force');
        $targets = collect();
        $page = 1;

        while (true) {
            $batch = $strava->activitiesPage($page, self::PER_PAGE);

            if ($batch === null) {
                $this->error("Strava request failed on page {$page}.");

                return null;
            }

            if ($batch === []) {
                break;
            }

            foreach ($batch as $summary) {
                if ((int) ($summary['total_photo_count'] ?? 0) < 1) {
                    continue;
                }

                $activity = $ours->get((string) $summary['id']);

                if (! $activity) {
                    continue;
                }

                if (! $force && $activity->getMedia('cover')->isNotEmpty()) {
                    continue;
                }

                $targets->push($activity);
            }

            $page++;
        }

        return $targets;
    }

    /**
     * Fetch and store photos for each target activity, pausing when the Strava
     * rate-limit window fills up.
     *
     * @param  Collection<int, Activity>  $targets
     */
    private function fetchPhotos(Strava $strava, SyncStravaPhotos $sync, Collection $targets): int
    {
        $stored = 0;
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

            if ($photos === null) {
                $this->warn("Failed to fetch photos for {$activity->source_id}");

                continue;
            }

            $count = $sync($activity, $photos);
            $stored += $count;

            $this->info("[{$stored}] {$activity->name} - {$count} photo(s)");
        }

        $this->info("Done. Stored {$stored} photo(s).");

        return self::SUCCESS;
    }
}
