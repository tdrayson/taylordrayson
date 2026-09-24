<?php

namespace App\Data\Aspects;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * A start and an end, in real timezones. Its presence is what makes .ics
 * available. Start and end carry their own zones, because a flight lands in a
 * different one from the one it left.
 */
final readonly class Span
{
    private function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public bool $allDay,
        public ?string $location,
    ) {}

    /** A start plus a length, both in one zone. */
    public static function moment(CarbonInterface $start, ?int $seconds, ?string $timezone, ?string $location = null): ?self
    {
        if ($seconds === null || $seconds <= 0) {
            return null;
        }

        $from = CarbonImmutable::parse($start->format('Y-m-d H:i:s'), $timezone ?: config('app.home_timezone'));

        return new self($from, $from->addSeconds($seconds), false, $location);
    }

    /** An explicit start and end, which may sit in different zones. */
    public static function between(CarbonInterface $start, ?CarbonInterface $end, ?string $timezone, ?string $location = null, bool $allDay = false): ?self
    {
        if ($end === null) {
            return null;
        }

        $zone = $timezone ?: config('app.home_timezone');

        return new self(
            CarbonImmutable::parse($start->format('Y-m-d H:i:s'), $zone),
            CarbonImmutable::parse($end->format('Y-m-d H:i:s'), $zone),
            $allDay,
            $location,
        );
    }

    /** An explicit start and end already carrying their own zones. */
    public static function across(CarbonImmutable $start, CarbonImmutable $end, ?string $location = null): self
    {
        return new self($start, $end, false, $location);
    }
}
