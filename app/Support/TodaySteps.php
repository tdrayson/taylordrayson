<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Today's running step count, as last fetched from Rovi.
 *
 * Cached rather than stored: this is an ambient live value for the status bar,
 * not history, so there is nothing to keep once the day is over. The day it was
 * recorded for is cached alongside it and checked on read, so a sync that stops
 * running shows nothing rather than yesterday's total presented as today's.
 */
class TodaySteps
{
    private const KEY = 'now.steps';

    /**
     * Kept a little over a day so a stale entry expires on its own even if the
     * date check never runs.
     */
    private const TTL_HOURS = 30;

    public static function put(int $steps, ?Carbon $for = null): void
    {
        Cache::put(self::KEY, [
            'date' => ($for ?? Carbon::today())->toDateString(),
            'steps' => $steps,
        ], now()->addHours(self::TTL_HOURS));
    }

    /**
     * Today's step count, or null when it was never fetched or the cached count
     * belongs to an earlier day.
     */
    public static function get(): ?int
    {
        $cached = Cache::get(self::KEY);

        if (! is_array($cached) || ($cached['date'] ?? null) !== Carbon::today()->toDateString()) {
            return null;
        }

        return isset($cached['steps']) ? (int) $cached['steps'] : null;
    }

    public static function forget(): void
    {
        Cache::forget(self::KEY);
    }
}
