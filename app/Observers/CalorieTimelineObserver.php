<?php

namespace App\Observers;

use App\Models\Calorie;
use App\Models\TimelineEntry;
use App\Queries\LoggingStreak;

class CalorieTimelineObserver
{
    public function saved(Calorie $calorie): void
    {
        // The streak is cached until midnight, so the first log of a new day
        // would otherwise not show up until tomorrow.
        LoggingStreak::forget();

        $date = $calorie->occurred_at->toDateString();

        $firstCalorie = Calorie::whereDate('occurred_at', $date)
            ->orderBy('id')
            ->first();

        if (! $firstCalorie) {
            return;
        }

        TimelineEntry::updateOrCreate(
            [
                'timelineable_type' => Calorie::class,
                'timelineable_id' => $firstCalorie->id,
            ],
            [
                'occurred_at' => $calorie->occurred_at->copy()->setTime(12, 0),
                'url_slug' => $firstCalorie->slug(),
            ],
        );
    }

    public function deleted(Calorie $calorie): void
    {
        LoggingStreak::forget();

        $date = $calorie->occurred_at->toDateString();

        $remaining = Calorie::whereDate('occurred_at', $date)
            ->where('id', '!=', $calorie->id)
            ->orderBy('id')
            ->first();

        if (! $remaining) {
            TimelineEntry::where('timelineable_type', Calorie::class)
                ->where('timelineable_id', $calorie->id)
                ->delete();

            return;
        }

        TimelineEntry::where('timelineable_type', Calorie::class)
            ->where('timelineable_id', $calorie->id)
            ->update(['timelineable_id' => $remaining->id]);
    }
}
