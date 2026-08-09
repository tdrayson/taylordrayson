<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class Instant
{
    /**
     * Resolve a stored local wall-clock time into the real moment it happened.
     * Two entries both reading 09:00 can be hours apart, so anything comparing
     * across zones must go through here first.
     */
    public static function for(CarbonInterface $wallClock, ?string $timezone): CarbonImmutable
    {
        $zone = $timezone ?: (string) config('app.home_timezone');

        return CarbonImmutable::parse($wallClock->format('Y-m-d H:i:s'), $zone)->utc();
    }
}
