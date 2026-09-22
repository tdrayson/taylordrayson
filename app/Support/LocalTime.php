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
            'time' => app(DisplayFormat::class)->time($local),
            'label' => app(DisplayFormat::class)->dateTime($local),
            'offset' => $local->format('P'),
            'iso' => $local->toIso8601String(),
        ];
    }

    /**
     * Render a true instant (a UTC timestamp, not a stored wall-clock reading)
     * by converting it into the given timezone before formatting.
     *
     * @return array{time: string, label: string, offset: string, iso: string}
     */
    public static function forInstant(CarbonInterface $instant, ?string $timezone): array
    {
        $zone = $timezone ?: (string) config('app.home_timezone');
        $local = CarbonImmutable::instance($instant)->setTimezone($zone);

        return [
            'time' => app(DisplayFormat::class)->time($local),
            'label' => app(DisplayFormat::class)->dateTime($local),
            'offset' => $local->format('P'),
            'iso' => $local->toIso8601String(),
        ];
    }
}
