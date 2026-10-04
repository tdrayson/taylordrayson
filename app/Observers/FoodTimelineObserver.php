<?php

namespace App\Observers;

use App\Models\Food;
use App\Models\Scopes\ListedScope;
use App\Models\TimelineEntry;
use App\Queries\CoffeesThisYear;
use App\Queries\LoggingStreak;
use App\Support\EntryInstant;
use App\Support\TimelineUrlSlug;

class FoodTimelineObserver
{
    public function saved(Food $food): void
    {
        // The streak and coffee count are cached until midnight, so the first
        // log of a new day would otherwise not show up until tomorrow.
        LoggingStreak::forget();
        CoffeesThisYear::forget();

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

        $entry = TimelineEntry::withoutGlobalScope(ListedScope::class)->updateOrCreate(
            [
                'dataset' => (new Food)->getMorphClass(),
                'entry_id' => $firstFood->id,
            ],
            [
                'occurred_at' => $occurredAt,
                'occurred_utc' => EntryInstant::utc($occurredAt, $firstFood->timezone()),
                'status' => $firstFood->status,
            ],
        );

        TimelineUrlSlug::ensure($entry, $firstFood->slug());
    }

    public function deleted(Food $food): void
    {
        LoggingStreak::forget();
        CoffeesThisYear::forget();

        $date = $food->occurred_at->toDateString();

        $remaining = Food::whereDate('occurred_at', $date)
            ->where('id', '!=', $food->id)
            ->orderBy('id')
            ->first();

        $dataset = (new Food)->getMorphClass();

        if (! $remaining) {
            TimelineEntry::withoutGlobalScope(ListedScope::class)->where('dataset', $dataset)
                ->where('entry_id', $food->id)
                ->delete();

            return;
        }

        TimelineEntry::withoutGlobalScope(ListedScope::class)->where('dataset', $dataset)
            ->where('entry_id', $food->id)
            ->update(['entry_id' => $remaining->id, 'status' => $remaining->status->value]);
    }
}
