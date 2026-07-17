<?php

namespace App\Console\Commands\Sync;

use App\Actions\StoreActivityStreams;
use App\Models\Activity;
use App\Services\Strava;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('strava:streams {--force : Refetch activities that already have streams}')]
#[Description('Backfill Strava altitude/speed/track/heart-rate streams onto route-bearing activities')]
class StravaStreams extends Command
{
    public function handle(StoreActivityStreams $store, Strava $strava): int
    {
        $query = Activity::query()
            ->where('source', 'strava')
            ->whereNotNull('meta->polyline');

        if (! $this->option('force')) {
            $query->whereNull('altitude');
        }

        $activities = $query->get();
        $done = 0;

        foreach ($activities as $activity) {
            if ($store($activity, $strava)) {
                $done++;
                $this->components->task("{$activity->name}");
            }
        }

        $this->components->info("Stored streams for {$done} of {$activities->count()} activities.");

        return self::SUCCESS;
    }
}
