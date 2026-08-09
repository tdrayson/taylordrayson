<?php

namespace App\Console\Commands\Sync;

use App\Services\Rovi;
use App\Support\TodaySteps;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('rovi:sync-steps')]
#[Description("Cache today's Rovi step count for the status bar")]
class RoviStepsSync extends Command
{
    /**
     * Fetch today's step count and cache it.
     *
     * Only today is requested: this feeds the live status bar rather than any
     * history, so there is nothing to backfill and no reconciliation to do. Rovi
     * keys each row by its date rather than by the `date` field, which holds the
     * time the row was written and can be months adrift.
     *
     * A failed or empty fetch leaves the previous value in place; it expires on
     * its own date check rather than being cleared here, so a transient outage
     * does not blank the status bar mid-day.
     */
    public function handle(Rovi $rovi): int
    {
        $today = Carbon::today()->toDateString();

        $rows = $rovi->steps(['from' => $today, 'to' => $today]);

        $steps = collect($rows)->firstWhere('id', $today)['steps'] ?? null;

        if ($steps === null) {
            $this->components->warn("Rovi returned no step count for {$today}.");

            return self::SUCCESS;
        }

        TodaySteps::put((int) $steps);

        $this->components->info(number_format((int) $steps).' steps today.');

        return self::SUCCESS;
    }
}
