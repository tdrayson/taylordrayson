<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class LocalTime
{
    /** @var array<int, string> Types stored as a whole day (midnight), shown without a time. */
    private const DAY_LEVEL_TYPES = ['calorie', 'sleep'];

    /**
     * Render a stored local wall-clock time in its timezone: the same clock
     * numbers, plus the correct (DST-aware) offset and machine-readable instant.
     * Day-level entries (calorie, sleep) are shown as a bare date, with no time
     * or offset, since their stored time is always midnight.
     *
     * @return array{time: string, label: string, offset: string, iso: string}
     */
    public static function for(CarbonInterface $occurredAt, ?string $timezone, bool $dateOnly = false): array
    {
        $zone = $timezone ?: (string) config('app.home_timezone');
        $local = CarbonImmutable::parse($occurredAt->format('Y-m-d H:i:s'), $zone);

        if ($dateOnly) {
            return [
                'time' => '',
                'label' => $local->format('D j M Y'),
                'offset' => '',
                'iso' => $local->format('Y-m-d'),
            ];
        }

        return [
            'time' => $local->format('g:ia'),
            'label' => $local->format('D j M Y, g:ia'),
            'offset' => $local->format('P'),
            'iso' => $local->toIso8601String(),
        ];
    }

    /**
     * Whether a timeline type is stored at day granularity (no meaningful time).
     */
    public static function isDayLevel(string $type): bool
    {
        return in_array($type, self::DAY_LEVEL_TYPES, true);
    }
}
