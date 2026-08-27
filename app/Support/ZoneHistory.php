<?php

namespace App\Support;

use App\Models\Flight;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Where flights say you were.
 *
 * A flight is an explicit "I changed zone at this moment", and unlike the
 * phone's last reading it still answers for an entry that arrives days late or
 * is imported from years ago.
 */
final class ZoneHistory
{
    /**
     * Longest span still treated as one trip.
     *
     * The flight history has gaps: an August 2012 outbound to Lanzarote has no
     * return recorded, so the next arrival home is 20 months later. Without a
     * cap that span would stamp two years of London evenings as foreign.
     */
    public const MAX_SPAN_DAYS = 30;

    /** @var Collection<int, Flight>|null */
    private ?Collection $flights = null;

    /** @var list<array{from: CarbonImmutable, to: CarbonImmutable, timezone: string}>|null */
    private ?array $spans = null;

    /** @var list<array{landedAt: CarbonImmutable, departure: string, arrival: string}>|null */
    private ?array $arrivals = null;

    /**
     * Time away from home, as spans between leaving and coming back.
     *
     * A flight whose arrival zone is not home starts one; the next flight
     * closes it, wherever it lands, so a leg to Cairo does not make the
     * fortnight before it Egyptian. Only the last flight of all can leave one
     * open, which means the trip is still running.
     *
     * @return list<array{from: CarbonImmutable, to: CarbonImmutable, timezone: string}>
     */
    public function spans(): array
    {
        if ($this->spans !== null) {
            return $this->spans;
        }

        $spans = [];
        $open = null;

        foreach ($this->flights() as $flight) {
            $at = CarbonImmutable::parse($flight->occurred_at);

            if ($open !== null) {
                $spans[] = [...$open, 'to' => $at];
                $open = null;
            }

            if ($flight->arrival_timezone !== EntryInstant::HOME) {
                $open = ['from' => $at, 'timezone' => $flight->arrival_timezone];
            }
        }

        // Still away: bounded like any other span rather than dropped, so
        // entries arriving today are placed while the trip is on.
        if ($open !== null) {
            $spans[] = [...$open, 'to' => $open['from']->addDays(self::MAX_SPAN_DAYS)];
        }

        return $this->spans = array_values(array_filter(
            $spans,
            fn (array $span): bool => $span['from']->diffInDays($span['to']) <= self::MAX_SPAN_DAYS,
        ));
    }

    /**
     * The zone of the span covering this reading, or null when none does, which
     * already means home.
     */
    public function zoneAt(CarbonInterface $occurredAt): ?string
    {
        foreach ($this->spans() as $span) {
            if ($occurredAt->between($span['from'], $span['to'])) {
                return $span['timezone'];
            }
        }

        return null;
    }

    /**
     * The most recent landing at or before this instant, as the zones either
     * side of it.
     *
     * @return array{landedAt: CarbonImmutable, departure: string, arrival: string}|null
     */
    public function arrivalBefore(CarbonInterface $instant): ?array
    {
        $found = null;

        foreach ($this->arrivals() as $arrival) {
            if ($arrival['landedAt']->greaterThan($instant)) {
                break;
            }

            $found = $arrival;
        }

        return $found;
    }

    /** Drop what was read, so a flight saved in this process is accounted for. */
    public function forget(): void
    {
        $this->flights = null;
        $this->spans = null;
        $this->arrivals = null;
    }

    /**
     * @return list<array{landedAt: CarbonImmutable, departure: string, arrival: string}>
     */
    private function arrivals(): array
    {
        if ($this->arrivals !== null) {
            return $this->arrivals;
        }

        $arrivals = [];

        foreach ($this->flights() as $flight) {
            $landedAt = $flight->arrivedAt();

            if ($landedAt === null || blank($flight->departure_timezone)) {
                continue;
            }

            $arrivals[] = [
                'landedAt' => $landedAt,
                'departure' => $flight->departure_timezone,
                'arrival' => $flight->arrival_timezone,
            ];
        }

        usort($arrivals, fn (array $a, array $b): int => $a['landedAt'] <=> $b['landedAt']);

        return $this->arrivals = $arrivals;
    }

    /**
     * Read once, then reused: a sync writing hundreds of rows must not cost a
     * query each. A flight with no arrival zone is excluded rather than skipped
     * later, so it cannot close an open span.
     *
     * @return Collection<int, Flight>
     */
    private function flights(): Collection
    {
        return $this->flights ??= Flight::query()
            ->whereNotNull('arrival_timezone')
            ->orderBy('occurred_at')
            ->get();
    }
}
