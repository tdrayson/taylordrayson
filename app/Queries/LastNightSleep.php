<?php

namespace App\Queries;

use App\Models\Sleep;
use Illuminate\Support\Carbon;

/**
 * The night just gone, or the one before it when that one has no record yet.
 * The single source of truth for "last night", so the /now widget and its
 * export cannot disagree about which record is the headline.
 */
final class LastNightSleep
{
    public function __invoke(): ?Sleep
    {
        $today = Carbon::today();

        return $this->forDate($today) ?? $this->forDate($today->copy()->subDay());
    }

    /** When a date carries more than one row, the most recently written one wins. */
    private function forDate(Carbon $date): ?Sleep
    {
        return Sleep::query()->listed()
            ->whereDate('occurred_at', $date)
            ->orderByDesc('id')
            ->first();
    }
}
