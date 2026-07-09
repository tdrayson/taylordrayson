<?php

namespace App\Console\Commands\Sync;

use App\Models\Activity;
use App\Services\Strava;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('strava:backfill-timezones {--per-page=200 : Activities per page}')]
#[Description('Backfill timezone and local occurred_at on existing Strava activities from the list endpoint')]
class BackfillStravaTimezones extends Command
{
    /**
     * Execute the console command.
     *
     * Pages all Strava summary activities, finds matching local Activity rows by
     * source_id, and updates occurred_at (to local wall-clock) and timezone
     * (IANA name). Does not write the CSV seed file.
     *
     * @return int Command exit code
     */
    public function handle(Strava $strava): int
    {
        if (! $strava->token()) {
            $this->error('Could not obtain a Strava access token.');

            return self::FAILURE;
        }

        $perPage = (int) $this->option('per-page');
        $page = 1;
        $updated = 0;

        do {
            $batch = $strava->activitiesPage($page, $perPage);

            if ($batch === null) {
                $this->error("Strava request failed on page {$page}.");

                return self::FAILURE;
            }

            foreach ($batch as $summary) {
                $activity = Activity::query()
                    ->where('source', 'strava')
                    ->where('source_id', (string) $summary['id'])
                    ->first();

                if ($activity === null) {
                    continue;
                }

                $localDate = $summary['start_date_local'] ?? $summary['start_date'];
                $ianaTimezone = Str::afterLast((string) ($summary['timezone'] ?? ''), ' ') ?: null;

                // Strava's start_date_local carries a Z; parse as UTC so the wall-clock digits are kept verbatim.
                $activity->update([
                    'occurred_at' => Carbon::parse($localDate, 'UTC')->format('Y-m-d H:i:s'),
                    'timezone' => $ianaTimezone,
                ]);

                $updated++;
            }

            $this->info("Page {$page}: ".count($batch).' activities scanned.');
            $page++;
        } while (count($batch) === $perPage);

        $this->info("Backfilled {$updated} activities.");

        return self::SUCCESS;
    }
}
