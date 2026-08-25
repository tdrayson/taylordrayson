<?php

use App\Models\Sleep;
use Illuminate\Database\Migrations\Migration;

/**
 * Store a sleep at the moment it ended rather than at midnight.
 *
 * `occurred_at` was the start of the day the sleep was filed under, so the
 * entry displayed its wake time through an accessor while sorting at midnight:
 * a 2:39pm nap rendered above a card labelled 12:00am. Storing the real moment
 * lets ordering, `occurred_utc`, the timezone and the card all derive from one
 * value.
 *
 * Four evening naps move to the day they happened on. `nightFor()` rolls
 * anything starting after 6pm to the next day, which is right for a night and
 * wrong for a doze at 18:03.
 */
return new class extends Migration
{
    public function up(): void
    {
        Sleep::query()->whereNotNull('wake_time')->eachById(function (Sleep $sleep): void {
            $sleep->forceFill(['occurred_at' => $sleep->wake_time])->save();
        });
    }

    /**
     * Back to the sleep day: midnight on the date the sleep was filed under.
     */
    public function down(): void
    {
        Sleep::query()->eachById(function (Sleep $sleep): void {
            $sleep->forceFill(['occurred_at' => $sleep->occurred_at->startOfDay()])->save();
        });
    }
};
