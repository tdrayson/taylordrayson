<?php

namespace App\Observers;

use App\Models\Food;
use App\Models\TimelineEntry;
use App\Queries\LoggingStreak;
use App\Support\EntryInstant;

class FoodTimelineObserver
{
    public function saved(Food $food): void
    {
        // The streak is cached until midnight, so the first log of a new day
        // would otherwise not show up until tomorrow.
        LoggingStreak::forget();

        $date = $food->occurred_at->toDateString();

        $firstFood = Food::whereDate('occurred_at', $date)
            ->orderBy('id')
            ->first();

        if (! $firstFood) {
            return;
        }

        // The end of the day the food belongs to: a daily total is only true
        // once the day is done, and it is the moment the card and its timezone
        // both read from.
        $occurredAt = $food->occurred_at->copy()->endOfDay();

        TimelineEntry::updateOrCreate(
            [
                'dataset' => (new Food)->getMorphClass(),
                'entry_id' => $firstFood->id,
            ],
            [
                'occurred_at' => $occurredAt,
                'occurred_utc' => EntryInstant::utc($occurredAt, $firstFood->timezone()),
                'url_slug' => $firstFood->slug(),
            ],
        );
    }

    public function deleted(Food $food): void
    {
        LoggingStreak::forget();

        $date = $food->occurred_at->toDateString();

        $remaining = Food::whereDate('occurred_at', $date)
            ->where('id', '!=', $food->id)
            ->orderBy('id')
            ->first();

        $dataset = (new Food)->getMorphClass();

        if (! $remaining) {
            TimelineEntry::where('dataset', $dataset)
                ->where('entry_id', $food->id)
                ->delete();

            return;
        }

        TimelineEntry::where('dataset', $dataset)
            ->where('entry_id', $food->id)
            ->update(['entry_id' => $remaining->id]);
    }
}
