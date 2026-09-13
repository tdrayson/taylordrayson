<?php

namespace App\Enums;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/** The bucket a streak counts in: consecutive days, weeks, months or years. */
enum Cadence: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    public function label(): string
    {
        return match ($this) {
            self::Day => 'Days',
            self::Week => 'Weeks',
            self::Month => 'Months',
            self::Year => 'Years',
        };
    }

    /** The bucket index a date falls in, so consecutive buckets differ by exactly one. */
    public function index(CarbonInterface $at): int
    {
        // Re-parsed as a bare date in UTC so daylight saving cannot shift a
        // day across a bucket boundary; occurred_at is local wall-clock already.
        $days = intdiv(CarbonImmutable::parse($at->format('Y-m-d'), 'UTC')->getTimestamp(), 86400);

        return match ($this) {
            self::Day => $days,
            // 1970-01-01 was a Thursday, so +3 puts week boundaries on Monday.
            self::Week => intdiv($days + 3, 7),
            self::Month => (int) $at->format('Y') * 12 + (int) $at->format('n'),
            self::Year => (int) $at->format('Y'),
        };
    }
}
