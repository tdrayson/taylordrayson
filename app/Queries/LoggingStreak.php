<?php

namespace App\Queries;

use App\Enums\Cadence;
use App\Models\Calorie;

/**
 * How many days in a row I have logged what I ate, counting back from today.
 *
 * A thin facade over {@see StreakDays}, so the sidebar reads the same cached
 * count every other food streak tag does.
 */
final class LoggingStreak
{
    public function __invoke(): int
    {
        return app(StreakDays::class)()[Calorie::class][Cadence::Day->value]['current'] ?? 0;
    }

    /** Drop the cached streaks, so the next read recomputes them. */
    public static function forget(): void
    {
        StreakDays::forget();
    }
}
