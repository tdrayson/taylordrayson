<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\Checkin;
use App\Models\Flight;
use App\Models\TimelineEntry;
use App\Support\EntryInstant;
use App\Support\VenueTimezone;
use App\Timeline\TypeRegistry;
use Carbon\CarbonInterface;
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
     * Longest span still treated as one trip.
     *
     * The flight history has gaps: an August 2012 outbound to Lanzarote has no
     * return recorded, so the next arrival home is 20 months later. Without a
     * cap that span would stamp two years of London evenings as foreign.
     */
    private const MAX_TRIP_DAYS = 30;

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

        // Read before the guess pass, which needs them to tell a zone this
        // command assigned from one Strava invented.
        $trips = $this->tripsFromFlights();

        $guessed = $this->clearGuessedActivityZones($trips, $dry);
        $this->components->info("Activities whose zone was an offset guess: {$guessed}");

        $checkins = $this->backfillCheckins($venues, $dry);
        $this->components->info("Check-ins given a venue timezone: {$checkins}");

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
     * Empty the zone on activities where Strava was guessing it.
     *
     * Without GPS, Strava names the first IANA zone matching the device's UTC
     * offset, so indoor sessions come back as Africa/Algiers for BST or
     * Africa/Abidjan for GMT. The instant stays correct either way, since the
     * offset matches; what changes is that the row stops claiming a continent
     * it was never on, and becomes correctable, because the trip pass below
     * only overrides a zone that is empty or home.
     *
     * Runs first for that reason: a flight can then place these properly, as
     * with the Basel trip that owns the lone Africa/Blantyre workout.
     *
     * A zone the trip pass already assigned is left alone, or each run would
     * wipe it and the next would write it back forever. Being inside a trip is
     * not enough on its own: an activity in Paris still claiming Africa/Algiers
     * is a guess, and clearing it is what lets the trip pass correct it.
     *
     * @param  list<array{from: Carbon, to: Carbon, timezone: string}>  $trips
     */
    private function clearGuessedActivityZones(array $trips, bool $dry): int
    {
        $changed = 0;

        $candidates = Activity::query()
            ->whereNotNull('timezone')
            ->where('timezone', '!=', self::HOME)
            ->whereNull('track');

        foreach ($candidates->lazy() as $activity) {
            if ($this->explainedByTrip($activity, $trips)) {
                continue;
            }

            if (! $dry) {
                $activity->forceFill(['timezone' => null])->save();
            }

            $changed++;
        }

        return $changed;
    }

    /**
     * Whether a trip covering this activity already accounts for its zone.
     *
     * @param  list<array{from: Carbon, to: Carbon, timezone: string}>  $trips
     */
    private function explainedByTrip(Activity $activity, array $trips): bool
    {
        foreach ($trips as $trip) {
            $covers = $activity->occurred_at->between($trip['from']->copy()->startOfDay(), $trip['to']->copy()->endOfDay());

            if ($covers && $activity->timezone === $trip['timezone']) {
                return true;
            }
        }

        return false;
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
        $legs = [];
        $open = null;

        foreach (Flight::query()->orderBy('occurred_at')->get() as $flight) {
            $arrival = $flight->arrival_timezone;
            $at = Carbon::parse($flight->occurred_at);

            if (blank($arrival)) {
                continue;
            }

            // Each leg is closed by the next flight, wherever it lands: a leg
            // to Cairo does not make the fortnight before it Egyptian.
            if ($open !== null) {
                $legs[] = [...$open, 'to' => $at];
                $open = null;
            }

            if ($arrival !== self::HOME) {
                $open = ['from' => $at, 'timezone' => $arrival];
            }
        }

        // A leg still open at the end has no return flight recorded, so its
        // span is unknown and it is dropped rather than guessed at.
        return array_values(array_filter(
            $legs,
            fn (array $leg): bool => $leg['from']->diffInDays($leg['to']) <= self::MAX_TRIP_DAYS,
        ));
    }

    /**
     * Give each entry the zone of the trip that covers it, and take it away
     * again when no trip does.
     *
     * Bounded by the flights themselves rather than by whole days. A departure
     * at 08:16 does not make the sleep that ended at 04:57 that morning
     * foreign, nor the television watched at 06:41 before leaving for the
     * airport.
     *
     * Only ever writes over an empty zone, a home one, or one belonging to some
     * trip: those are the values this command assigns, so re-running corrects
     * its own earlier answers. A zone from anywhere else is left alone.
     *
     * @param  list<array{from: Carbon, to: Carbon, timezone: string}>  $trips
     */
    private function backfillTrips(array $trips, bool $dry): int
    {
        $changed = 0;
        $tripZones = array_values(array_unique(array_column($trips, 'timezone')));

        foreach (TypeRegistry::all() as $definition) {
            $model = $definition['model'];

            // A check-in knows exactly where it was, so a trip's zone is
            // strictly worse: an airport check-in on the outbound day would be
            // stamped with the destination. One that could not be resolved is
            // better left empty, which already means home.
            if ($model === Checkin::class || ! $this->hasTimezoneColumn($model)) {
                continue;
            }

            $candidates = $model::query()->where(
                fn ($where) => $where->whereNull('timezone')
                    ->orWhere('timezone', self::HOME)
                    ->orWhereIn('timezone', $tripZones),
            );

            foreach ($candidates->lazy() as $entry) {
                $current = $entry->getAttributes()['timezone'] ?? null;
                $zone = $this->tripZoneFor($entry->occurred_at, $trips);

                // Empty already means home, so writing it back says nothing.
                if ($zone === $current || ($zone === null && $current === self::HOME)) {
                    continue;
                }

                if (! $dry) {
                    $entry->forceFill(['timezone' => $zone])->save();
                }

                $changed++;
            }
        }

        return $changed;
    }

    /**
     * The zone of the trip an entry falls inside, or null when none does.
     *
     * @param  list<array{from: Carbon, to: Carbon, timezone: string}>  $trips
     */
    private function tripZoneFor(CarbonInterface $occurredAt, array $trips): ?string
    {
        foreach ($trips as $trip) {
            if ($occurredAt->between($trip['from'], $trip['to'])) {
                return $trip['timezone'];
            }
        }

        return null;
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
