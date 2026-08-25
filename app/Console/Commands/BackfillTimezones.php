<?php

namespace App\Console\Commands;

use App\Models\Checkin;
use App\Models\Flight;
use App\Models\TimelineEntry;
use App\Support\EntryInstant;
use App\Support\VenueTimezone;
use App\Timeline\TypeRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

#[Signature('timezones:backfill {--dry-run : Report what would change without writing}')]
#[Description('Give historical entries the timezone they happened in, and derive every instant')]
class BackfillTimezones extends Command
{
    /** Where I live, and what an entry with no better answer is assumed to be. */
    private const HOME = EntryInstant::HOME;

    /** As Foursquare spells it, for skipping the check-ins already at home. */
    private const HOME_COUNTRY = 'United Kingdom';

    /**
     * Fill in the timezones history never recorded, then derive the instants.
     *
     * Three passes, weakest signal last:
     *
     * 1. Check-ins know exactly where they were, so their venue's coordinate
     *    gives the zone directly.
     * 2. A flight is an explicit "I changed zone on this date", so pairing each
     *    departure from home with the next arrival back bounds a trip, and
     *    everything inside one belongs to where the trip was.
     * 3. `occurred_utc` is then derived for every entry, which is what the
     *    timeline orders by.
     *
     * Re-runnable: each pass only writes where the answer would change, so a
     * second run reports nothing and a partial run resumes cleanly.
     */
    public function handle(VenueTimezone $venues): int
    {
        $dry = (bool) $this->option('dry-run');

        $checkins = $this->backfillCheckins($venues, $dry);
        $this->components->info("Check-ins given a venue timezone: {$checkins}");

        $trips = $this->tripsFromFlights();
        $this->components->info('Trips found from flights: '.count($trips));

        $travelled = $this->backfillTrips($trips, $dry);
        $this->components->info("Entries inside a trip given its timezone: {$travelled}");

        $instants = $this->deriveInstants($dry);
        $this->components->info("Instants derived: {$instants}");

        if ($dry) {
            // Each pass counts as though the earlier ones had not run, so the
            // trip and instant figures here are higher than a real run's.
            $this->components->warn('Dry run: nothing was written.');
        }

        return self::SUCCESS;
    }

    /**
     * Resolve each check-in's zone from its venue coordinate, and re-render
     * `occurred_at` as the wall clock there rather than the server's.
     *
     * The stored reading came from `date()` on a UTC timestamp, so it is the
     * server's clock: converting back through UTC recovers the true instant.
     */
    private function backfillCheckins(VenueTimezone $venues, bool $dry): int
    {
        $changed = 0;

        // Home is already what an empty column means, so the ~2,265 UK
        // check-ins need no lookup at all. Nulls are included in case a
        // country was never recorded.
        $away = Checkin::query()
            ->whereNull('timezone')
            ->where(fn ($query) => $query->whereNull('country')->orWhere('country', '!=', self::HOME_COUNTRY));

        foreach ($away->lazy() as $checkin) {
            $zone = $venues->forCoordinate($checkin->latitude, $checkin->longitude);

            if ($zone === null || $zone === self::HOME) {
                // Home is what the column already means when empty, so writing
                // it changes nothing but would hide which rows were resolved.
                continue;
            }

            $instant = Carbon::parse((string) $checkin->getRawOriginal('occurred_at'), self::HOME);

            if (! $dry) {
                $checkin->forceFill([
                    'timezone' => $zone,
                    'occurred_at' => $instant->copy()->setTimezone($zone)->format('Y-m-d H:i:s'),
                ])->save();
            }

            $changed++;
        }

        return $changed;
    }

    /**
     * Trips, as spans between leaving home and coming back.
     *
     * A flight whose arrival zone is not home starts one; the next flight
     * arriving home ends it. Flights already carry both zones, so no lookup is
     * needed.
     *
     * @return list<array{from: Carbon, to: Carbon, timezone: string}>
     */
    private function tripsFromFlights(): array
    {
        $trips = [];
        $open = null;

        foreach (Flight::query()->orderBy('occurred_at')->get() as $flight) {
            $arrival = $flight->arrival_timezone;

            if (blank($arrival)) {
                continue;
            }

            if ($arrival === self::HOME) {
                if ($open !== null) {
                    $trips[] = [...$open, 'to' => Carbon::parse($flight->occurred_at)];
                    $open = null;
                }

                continue;
            }

            // A second outbound leg while already away extends the trip rather
            // than starting a new one, but the zone follows the newest arrival.
            $open = ['from' => Carbon::parse($open['from'] ?? $flight->occurred_at), 'timezone' => $arrival];
        }

        return $trips;
    }

    /**
     * Stamp entries falling inside a trip with where that trip was, leaving
     * anything that already knows its own zone alone.
     *
     * @param  list<array{from: Carbon, to: Carbon, timezone: string}>  $trips
     */
    private function backfillTrips(array $trips, bool $dry): int
    {
        $changed = 0;

        foreach (TypeRegistry::all() as $definition) {
            $model = $definition['model'];

            // A check-in knows exactly where it was, so a trip's zone is
            // strictly worse: an airport check-in on the outbound day would be
            // stamped with the destination. One that could not be resolved is
            // better left empty, which already means home.
            if ($model === Checkin::class || ! $this->hasTimezoneColumn($model)) {
                continue;
            }

            foreach ($trips as $trip) {
                $query = $model::query()
                    ->whereNull('timezone')
                    ->whereBetween('occurred_at', [$trip['from']->toDateString().' 00:00:00', $trip['to']->toDateString().' 23:59:59']);

                $changed += $dry
                    ? $query->count()
                    : $query->update(['timezone' => $trip['timezone']]);
            }
        }

        return $changed;
    }

    /** Whether a model's table carries a timezone of its own to fill. */
    private function hasTimezoneColumn(string $model): bool
    {
        /** @var Model $instance */
        $instance = new $model;

        return $instance->getConnection()
            ->getSchemaBuilder()
            ->hasColumn($instance->getTable(), 'timezone');
    }

    /**
     * Derive `occurred_utc` for every spine row from its entry's local reading
     * and zone. This is what the timeline orders by.
     */
    private function deriveInstants(bool $dry): int
    {
        $changed = 0;

        foreach (TimelineEntry::query()->with('timelineable')->lazy() as $entry) {
            $model = $entry->timelineable;

            if ($model === null) {
                continue;
            }

            $zone = method_exists($model, 'timezone') ? $model->timezone() : null;
            $utc = EntryInstant::utc($entry->occurred_at, $zone);

            if ($utc === null || $entry->occurred_utc?->equalTo($utc)) {
                continue;
            }

            if (! $dry) {
                $entry->forceFill(['occurred_utc' => $utc])->saveQuietly();
            }

            $changed++;
        }

        return $changed;
    }
}
