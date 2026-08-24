<?php

namespace App\Queries;

use App\Models\Calorie;
use App\Support\SqlDate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * How many days in a row I have logged what I ate, counting back from today.
 *
 * Today is allowed to be empty without breaking the run: the count is a
 * property of the days already finished, and a streak should not appear to
 * reset every midnight and restore itself at breakfast.
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
        $logged = Calorie::query()
            ->toBase()
            ->selectRaw(SqlDate::date('occurred_at').' as day')
            ->distinct()
            ->pluck('day')
            ->flip();

        if ($logged->isEmpty()) {
            return 0;
        }

        $cursor = Carbon::today();

        // Yesterday is the last day that can be judged complete, so an empty
        // today leaves the run intact rather than ending it.
        if (! $logged->has($cursor->toDateString())) {
            $cursor->subDay();
        }

        $days = 0;

        while ($logged->has($cursor->toDateString())) {
            $days++;
            $cursor->subDay();
        }

        return $days;
    }
}
