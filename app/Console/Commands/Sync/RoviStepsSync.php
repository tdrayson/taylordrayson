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
     * Fetch today's step count and cache it for the live status bar. Rows are
     * keyed by their own date, not Rovi's `date` field, which holds the write
     * time and can be months adrift. A failed fetch leaves the previous value.
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
