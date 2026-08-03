<?php

namespace App\Queries;

use App\Models\Concerns\Timelineable;
use App\Models\Trip;
use App\Support\Instant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * The trip an entry falls inside, or null. Lets an entry page point back at its
 * trip without a trip ever having to appear in the timeline itself.
 *
 * Overlapping trips are not expected; the earliest-starting match wins.
 */
final class TripForEntry
{
    /**
     * Widen the candidate window by a day either side of the entry, for the
     * same reason TripEntries pads its own: the stored values are wall-clock
     * and only become comparable once resolved against their zones.
     */
    private const PAD_DAYS = 1;

    public function __invoke(Model&Timelineable $model): ?Trip
    {
        if ($model->occurred_at === null) {
            return null;
        }

        $occurred = Instant::for($model->occurred_at, $model->timezone());
        $wallClock = CarbonImmutable::parse($model->occurred_at->format('Y-m-d H:i:s'));

        return Trip::query()
            ->where('starts_at', '<=', $wallClock->addDays(self::PAD_DAYS))
            ->where('ends_at', '>=', $wallClock->subDays(self::PAD_DAYS))
            ->orderBy('starts_at')
            ->get()
            ->first(fn (Trip $trip): bool => $occurred->betweenIncluded(
                Instant::for($trip->starts_at, $trip->timezone),
                Instant::for($trip->ends_at, $trip->timezone),
            ));
    }
}
