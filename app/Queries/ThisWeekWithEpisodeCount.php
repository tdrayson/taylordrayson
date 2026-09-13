<?php

namespace App\Queries;

use App\Models\ThisWeekWith;
use Illuminate\Support\Facades\Cache;

/**
 * How many episodes of the podcast exist, for the timeline's intro copy.
 *
 * Cached until midnight rather than counted on every timeline load: episodes
 * arrive on a weekly cron, so a figure in a sentence can be a day behind
 * without anyone being misled.
 */
final class ThisWeekWithEpisodeCount
{
    private const KEY = 'count.this-week-with-episodes';

    public function __invoke(): int
    {
        return Cache::remember(self::KEY, now()->endOfDay(), fn (): int => ThisWeekWith::query()->count());
    }

    /** Drop the cached count, so the next read recomputes it. */
    public static function forget(): void
    {
        Cache::forget(self::KEY);
    }
}
