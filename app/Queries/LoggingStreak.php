<?php

namespace App\Queries;

use App\Enums\Cadence;
use App\Models\Food;

/**
 * How many days in a row I have logged what I ate, counting back from the most
 * recent logged day.
 *
 * The count is a property of the days already logged, so neither an empty
 * today nor a sync running days behind reads as a broken streak.
 */
final class LoggingStreak
{
    /** The sidebar streak, read from the same computation as {streak.current}. */
    public function __invoke(): int
    {
        return app(StreakDays::class)->for(Food::class, Cadence::Day)['current'];
    }

    /** Drop the cached streaks, so the sidebar and the streak tags recount together. */
    public static function forget(): void
    {
        StreakDays::forget();
    }
}
