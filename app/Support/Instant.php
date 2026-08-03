<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class Instant
{
    /**
     * Resolve a stored local wall-clock time into the real moment it happened.
     *
     * Timeline models store the clock numbers they were recorded against plus
     * the zone those numbers belong to, so two entries reading 09:00 can be
     * hours apart. Anything comparing entries across zones has to resolve them
     * to a common instant first, which is what this returns. Mirrors how
     * LocalTime reads the same pair for display.
     */
    public static function for(CarbonInterface $wallClock, ?string $timezone): CarbonImmutable
    {
        $zone = $timezone ?: (string) config('app.home_timezone');

        return CarbonImmutable::parse($wallClock->format('Y-m-d H:i:s'), $zone)->utc();
    }
}
