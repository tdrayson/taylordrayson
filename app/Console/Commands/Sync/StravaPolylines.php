<?php

namespace App\Console\Commands\Sync;

use App\Enums\Source;
use App\Models\Activity;
use App\Services\Strava\Client;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('strava:polylines {--limit=0 : Max activities to fetch (0 = all)} {--force : Re-fetch even if polyline exists}')]
#[Description('Fetch polylines from Strava API for run/walk/ride activities')]
class StravaPolylines extends Command
{
    private const RATE_LIMIT = 95;

    private const RATE_WINDOW = 900;

    private const POLYLINE_TYPES = ['run', 'walk', 'ride', 'e-bike-ride'];

    public function handle(Client $strava): int
    {
        if (! $strava->token()) {
            $this->error('Could not obtain a Strava access token.');

            return self::FAILURE;
        }

        $query = Activity::query()
            ->where('source', Source::Strava->value)
            ->whereNotNull('source_id')
            ->whereIn('type', self::POLYLINE_TYPES);

        if (! $this->option('force')) {
            $query->whereNull('meta->polyline');
        }

        $activities = $query->orderBy('occurred_at')->get();

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $activities = $activities->take($limit);
        }

        $this->info("Found {$activities->count()} activities to fetch polylines for.");

        if ($activities->isEmpty()) {
            return self::SUCCESS;
        }

        $fetched = 0;
        $requestsInWindow = 0;
        $windowStart = time();

        foreach ($activities as $activity) {
            if ($requestsInWindow >= self::RATE_LIMIT) {
                $elapsed = time() - $windowStart;
                $wait = self::RATE_WINDOW - $elapsed;
                if ($wait > 0) {
                    $this->info("Rate limit reached. Waiting {$wait}s...");
                    sleep($wait);
                }
                $requestsInWindow = 0;
                $windowStart = time();
            }

            $data = $strava->activity($activity->source_id);

            $requestsInWindow++;

            if ($data === null) {
                $this->warn("Failed to fetch {$activity->source_id}");

                continue;
            }

            $polyline = $data['map']['polyline'] ?? null;

            if ($polyline) {
                $meta = $activity->meta ?? [];
                $meta['polyline'] = $polyline;
                $activity->update(['meta' => $meta]);

                $fetched++;
            }

            $this->info("[{$fetched}/{$activities->count()}] {$activity->name} - ".($polyline ? 'polyline saved' : 'no polyline'));
        }

        $this->info("Done. Fetched polylines for {$fetched} activities.");

        return self::SUCCESS;
    }
}
