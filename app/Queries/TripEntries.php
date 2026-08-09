<?php

namespace App\Queries;

use App\Models\TimelineEntry;
use App\Models\Trip;
use App\Support\Instant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Every timeline entry falling inside a trip's window, oldest first.
 *
 * A trip owns no content, so this is the whole feature: the page is this query
 * and nothing is ever filed into a trip.
 */
final class TripEntries
{
    /**
     * Real-world UTC offsets top out either side of 14 hours, so padding the
     * wall-clock prefilter by a day cannot drop an entry the precise instant
     * comparison would have kept.
     */
    private const PAD_DAYS = 1;

    /**
     * @return Collection<int, TimelineEntry>
     */
    public function __invoke(Trip $trip): Collection
    {
        $start = Instant::for($trip->starts_at, $trip->timezone);
        $end = Instant::for($trip->ends_at, $trip->timezone);

        return $this->candidates($trip)
            ->filter(function (TimelineEntry $entry) use ($start, $end): bool {
                $occurred = Instant::for($entry->occurred_at, $entry->timelineable->timezone());

                return $occurred->betweenIncluded($start, $end);
            })
            ->sortBy(fn (TimelineEntry $entry): int => $entry->occurred_at->getTimestamp())
            ->values();
    }

    /**
     * The padded wall-clock superset. SQLite cannot resolve each row's zone in
     * SQL, so the window is widened here and narrowed precisely in PHP once the
     * models (and with them their timezones) are hydrated. They are hydrated
     * for rendering regardless, so the pass costs nothing beyond the few extra
     * rows the padding pulls in.
     *
     * @return Collection<int, TimelineEntry>
     */
    private function candidates(Trip $trip): Collection
    {
        return TimelineEntry::query()
            ->withCardRelations()
            ->whereBetween('occurred_at', [
                CarbonImmutable::parse($trip->starts_at->format('Y-m-d H:i:s'))->subDays(self::PAD_DAYS),
                CarbonImmutable::parse($trip->ends_at->format('Y-m-d H:i:s'))->addDays(self::PAD_DAYS),
            ])
            ->get()
            ->filter(fn (TimelineEntry $entry): bool => $entry->timelineable !== null);
    }
}
