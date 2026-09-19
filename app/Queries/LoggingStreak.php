<?php

namespace App\Queries;

use App\Models\Food;
use App\Support\SqlDate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * How many days in a row I have logged what I ate, counting back from the most
 * recent logged day.
 *
 * The count is a property of the days already logged, so neither an empty
 * today nor a sync running days behind reads as a broken streak.
 */
final class LoggingStreak
{
    private const KEY = 'streak.food';

    /**
     * The current streak in days, computed once per day.
     *
     * Every page shares this for the sidebar, so it is cached until midnight
     * rather than counting back over 25,000 food rows per request.
     */
    public function __invoke(): int
    {
        return Cache::remember(self::KEY, now()->endOfDay(), fn (): int => $this->count());
    }

    /** Drop the cached count, so the next read recomputes it. */
    public static function forget(): void
    {
        Cache::forget(self::KEY);
    }

    private function count(): int
    {
        $logged = Food::query()
            ->toBase()
            ->selectRaw(SqlDate::date('occurred_at').' as day')
            ->distinct()
            ->pluck('day');

        $today = Carbon::today()->toDateString();

        // The run starts at the last day actually logged, not at today, so a
        // late sync holds the streak. A future-dated row cannot inflate it.
        $latest = $logged->filter(fn (string $day): bool => $day <= $today)->max();

        if ($latest === null) {
            return 0;
        }

        $logged = $logged->flip();
        $cursor = Carbon::parse($latest);
        $days = 0;

        while ($logged->has($cursor->toDateString())) {
            $days++;
            $cursor->subDay();
        }

        return $days;
    }
}
