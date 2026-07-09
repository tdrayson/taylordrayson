<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class LocalTime
{
    /**
     * Render a stored local wall-clock time in its timezone: the same clock
     * numbers, plus the correct (DST-aware) offset and machine-readable instant.
     * Day-granular types pass a standardised time instead (food is stored at
     * midnight; sleep displays its wake time), so every card gets a real,
     * linkable timestamp.
     *
     * @return array{time: string, label: string, offset: string, iso: string}
     */
    public static function for(CarbonInterface $occurredAt, ?string $timezone): array
    {
        $zone = $timezone ?: (string) config('app.home_timezone');
        $local = CarbonImmutable::parse($occurredAt->format('Y-m-d H:i:s'), $zone);

        return [
            'time' => $local->format('g:ia'),
            'label' => $local->format('D j M Y, g:ia'),
            'offset' => $local->format('P'),
            'iso' => $local->toIso8601String(),
        ];
    }
}
