<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * The zone to stamp on an entry that arrived without one.
 *
 * Strongest signal first: the phone's last reading, then where flights say you
 * were, then null, which already renders as home. An entry that knows its own
 * position never reaches here, because its column is filled by the time it is
 * saved.
 */
final class EntryZone
{
    /**
     * How long after landing a phone still naming the departure zone counts as
     * stale rather than as having gone back.
     *
     * Without a bound, flying out and returning overland reads as staleness for
     * good: the phone correctly says home, and the rule would keep overruling
     * it with the destination.
     */
    private const STALE_AFTER_LANDING_HOURS = 24;

    public function __construct(
        private readonly AmbientZone $ambient,
        private readonly ZoneHistory $history,
    ) {}

    public function forEntryAt(CarbonInterface $occurredAt): ?string
    {
        $phone = $this->ambient->forEntryAt($occurredAt);

        // Nothing from the phone: it has not reported lately, it is at home, or
        // the entry is too old for today's location to say anything about it.
        // Flights still answer, including for a row imported from years ago.
        if ($phone === null) {
            return $this->history->zoneAt($occurredAt);
        }

        return $this->justLanded($phone, $occurredAt) ?? $phone;
    }

    /**
     * The arrival zone of a flight this entry has only just followed, when the
     * phone is still naming the airport it left from.
     *
     * A stale device lags: it reports a zone you were in, never one you have
     * not reached yet. The phone agreeing with the departure zone is that
     * signature, and the only case worth overruling. Landing where the phone
     * already says, or it naming a third zone entirely (driving on from where
     * you landed), both mean it is current.
     */
    private function justLanded(string $phone, CarbonInterface $occurredAt): ?string
    {
        $instant = EntryInstant::utc($occurredAt, $phone);

        if ($instant === null) {
            return null;
        }

        $arrival = $this->history->arrivalBefore($instant);

        if ($arrival === null || $arrival['departure'] !== $phone || $arrival['arrival'] === $phone) {
            return null;
        }

        return $instant->lessThanOrEqualTo($arrival['landedAt']->addHours(self::STALE_AFTER_LANDING_HOURS))
            ? $arrival['arrival']
            : null;
    }
}
