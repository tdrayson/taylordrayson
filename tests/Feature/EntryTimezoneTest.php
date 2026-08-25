<?php

use App\Models\Activity;
use App\Models\Checkin;
use App\Models\Flight;
use App\Models\Media;
use App\Models\Sleep;
use App\Models\TimelineEntry;
use App\Support\EntryInstant;
use App\Support\VenueTimezone;

/*
 * `occurred_at` is local wall-clock, so ordering by it sorts by what the clock
 * said: 09:00 in London and 09:00 in New York tie despite being five hours
 * apart. `occurred_utc` is derived so the timeline can order by when things
 * actually happened, while grouping stays on the local day.
 */

describe('EntryInstant', function () {
    it('reads a wall-clock time in the entry\'s own zone', function () {
        expect(EntryInstant::utc('2022-10-13 09:00:00', 'Europe/London')->toDateTimeString())
            ->toBe('2022-10-13 08:00:00')
            ->and(EntryInstant::utc('2022-10-13 09:00:00', 'America/New_York')->toDateTimeString())
            ->toBe('2022-10-13 13:00:00');
    });

    it('falls back to home for a missing or unrecognised zone', function () {
        expect(EntryInstant::zone(null))->toBe('Europe/London')
            ->and(EntryInstant::zone('Mars/Olympus'))->toBe('Europe/London');
    });
});

it('derives the instant whenever an entry is saved', function () {
    $activity = Activity::factory()->create([
        'occurred_at' => '2022-10-13 09:00:00',
        'timezone' => 'America/New_York',
    ]);

    expect($activity->timelineEntry->occurred_utc->toDateTimeString())->toBe('2022-10-13 13:00:00');

    // Recomputed rather than written once, so it cannot drift from its inputs.
    $activity->update(['timezone' => 'Europe/London']);

    expect($activity->fresh()->timelineEntry->occurred_utc->toDateTimeString())->toBe('2022-10-13 08:00:00');
});

it('orders by when things happened, not by what the clock said', function () {
    $london = Activity::factory()->create(['occurred_at' => '2022-10-13 09:00:00', 'timezone' => 'Europe/London']);
    $newYork = Activity::factory()->create(['occurred_at' => '2022-10-13 09:00:00', 'timezone' => 'America/New_York']);

    // Same wall clock, five hours apart. Ordering by occurred_at would tie.
    $order = TimelineEntry::query()->orderByInstant('asc')->pluck('timelineable_id')->all();

    expect($order)->toBe([$london->id, $newYork->id]);
});

describe('timezones:backfill', function () {
    beforeEach(function () {
        // Every coordinate resolves to New York, so the pass can be observed
        // without reaching the network.
        $this->mock(VenueTimezone::class, function ($mock) {
            $mock->shouldReceive('forCoordinate')->andReturn('America/New_York');
        });
    });

    it('gives a foreign check-in its venue zone and the clock that was on the wall', function () {
        $checkin = Checkin::factory()->create([
            // As date() rendered it: the server's clock, not the venue's.
            'occurred_at' => '2022-10-14 01:26:55',
            'country' => 'United States',
            'latitude' => 40.75,
            'longitude' => -73.99,
            'timezone' => null,
        ]);

        $this->artisan('timezones:backfill')->assertSuccessful();

        $checkin->refresh();

        // 01:26 in London is 20:26 the evening before in New York, so it moves
        // to the day it actually happened on.
        expect($checkin->timezone)->toBe('America/New_York')
            ->and($checkin->occurred_at->toDateTimeString())->toBe('2022-10-13 20:26:55');
    });

    it('leaves a check-in at home alone, since empty already means home', function () {
        $checkin = Checkin::factory()->create([
            'occurred_at' => '2022-10-14 01:26:55',
            'country' => 'United Kingdom',
            'timezone' => null,
        ]);

        $this->artisan('timezones:backfill')->assertSuccessful();

        expect($checkin->fresh()->timezone)->toBeNull()
            ->and($checkin->fresh()->occurred_at->toDateTimeString())->toBe('2022-10-14 01:26:55');
    });

    it('stamps entries inside a trip with where the trip was', function () {
        Flight::factory()->create([
            'occurred_at' => '2022-10-13 08:00:00',
            'arrival_timezone' => 'America/New_York',
        ]);
        Flight::factory()->create([
            'occurred_at' => '2022-10-20 18:00:00',
            'arrival_timezone' => 'Europe/London',
        ]);

        $away = Sleep::factory()->create(['occurred_at' => '2022-10-16 23:00:00', 'timezone' => null]);
        $home = Sleep::factory()->create(['occurred_at' => '2022-11-16 23:00:00', 'timezone' => null]);

        $this->artisan('timezones:backfill')->assertSuccessful();

        expect($away->fresh()->timezone)->toBe('America/New_York')
            ->and($home->fresh()->timezone)->toBeNull();
    });

    // A check-in knows exactly where it was, so a trip's zone is strictly
    // worse: an airport check-in on the outbound day is still at the airport.
    it('never lets a trip overrule a check-in', function () {
        Flight::factory()->create(['occurred_at' => '2022-10-13 08:00:00', 'arrival_timezone' => 'America/New_York']);
        Flight::factory()->create(['occurred_at' => '2022-10-20 18:00:00', 'arrival_timezone' => 'Europe/London']);

        $gatwick = Checkin::factory()->create([
            'occurred_at' => '2022-10-13 06:00:00',
            'country' => 'United Kingdom',
            'timezone' => null,
        ]);

        $this->artisan('timezones:backfill')->assertSuccessful();

        expect($gatwick->fresh()->timezone)->toBeNull();
    });

    // The flight history has gaps. An August 2012 outbound to Lanzarote has no
    // return recorded, so the next arrival home is 20 months later; without a
    // cap that span stamped two years of London evenings as foreign.
    it('ignores a trip with no return flight rather than running it for months', function () {
        Flight::factory()->create(['occurred_at' => '2012-08-27 09:00:00', 'arrival_timezone' => 'Atlantic/Canary']);
        Flight::factory()->create(['occurred_at' => '2014-04-11 18:00:00', 'arrival_timezone' => 'Europe/London']);

        $home = Sleep::factory()->create(['occurred_at' => '2013-06-15 23:00:00', 'timezone' => null]);

        $this->artisan('timezones:backfill')->assertSuccessful();

        expect($home->fresh()->timezone)->toBeNull();
    });

    // A later leg must not rewrite the zone of the days before it.
    it('gives each leg of a trip its own zone', function () {
        Flight::factory()->create(['occurred_at' => '2022-10-13 08:00:00', 'arrival_timezone' => 'America/New_York']);
        Flight::factory()->create(['occurred_at' => '2022-10-19 12:00:00', 'arrival_timezone' => 'America/Chicago']);
        Flight::factory()->create(['occurred_at' => '2022-10-25 18:00:00', 'arrival_timezone' => 'Europe/London']);

        $first = Sleep::factory()->create(['occurred_at' => '2022-10-15 23:00:00', 'timezone' => null]);
        $second = Sleep::factory()->create(['occurred_at' => '2022-10-21 23:00:00', 'timezone' => null]);

        $this->artisan('timezones:backfill')->assertSuccessful();

        expect($first->fresh()->timezone)->toBe('America/New_York')
            ->and($second->fresh()->timezone)->toBe('America/Chicago');
    });

    // TraktSync stamps every watch Europe/London on the assumption of watching
    // from the UK, which a trip disproves.
    it('overrules a home zone that was only ever an assumption', function () {
        Flight::factory()->create(['occurred_at' => '2022-10-13 08:00:00', 'arrival_timezone' => 'America/New_York']);
        Flight::factory()->create(['occurred_at' => '2022-10-25 18:00:00', 'arrival_timezone' => 'Europe/London']);

        $abroad = Media::factory()->create(['occurred_at' => '2022-10-18 20:00:00', 'timezone' => 'Europe/London']);
        $atHome = Media::factory()->create(['occurred_at' => '2022-12-18 20:00:00', 'timezone' => 'Europe/London']);

        $this->artisan('timezones:backfill')->assertSuccessful();

        expect($abroad->fresh()->timezone)->toBe('America/New_York')
            ->and($atHome->fresh()->timezone)->toBe('Europe/London');
    });

    // The trip pass assigns zones to activities with no GPS, which is exactly
    // what the guess pass looks for. Without an exception the two would fight,
    // one clearing what the other wrote, on every run forever.
    it('leaves a zone the trip pass assigned alone on a later run', function () {
        Flight::factory()->create(['occurred_at' => '2025-06-03 08:00:00', 'arrival_timezone' => 'Europe/Paris']);
        Flight::factory()->create(['occurred_at' => '2025-06-08 18:00:00', 'arrival_timezone' => 'Europe/London']);

        $workout = Activity::factory()->create([
            'occurred_at' => '2025-06-04 12:00:00',
            'timezone' => 'Africa/Blantyre',
            'track' => null,
        ]);

        $this->artisan('timezones:backfill')->assertSuccessful();
        expect($workout->fresh()->timezone)->toBe('Europe/Paris');

        $this->artisan('timezones:backfill')
            ->expectsOutputToContain('Activities whose zone was an offset guess: 0')
            ->assertSuccessful();

        expect($workout->fresh()->timezone)->toBe('Europe/Paris');
    });

    // Being inside a trip is not a free pass: a zone that disagrees with where
    // the trip was is still a guess, and clearing it is what lets it be fixed.
    it('still clears a guess that disagrees with the trip around it', function () {
        Flight::factory()->create(['occurred_at' => '2025-06-03 08:00:00', 'arrival_timezone' => 'Europe/Paris']);
        Flight::factory()->create(['occurred_at' => '2025-06-08 18:00:00', 'arrival_timezone' => 'Europe/London']);

        $workout = Activity::factory()->create([
            'occurred_at' => '2025-06-04 12:00:00',
            'timezone' => 'Africa/Algiers',
            'track' => null,
        ]);

        $this->artisan('timezones:backfill')->assertSuccessful();

        expect($workout->fresh()->timezone)->toBe('Europe/Paris');
    });

    it('changes nothing on a second run', function () {
        Checkin::factory()->create([
            'occurred_at' => '2022-10-14 01:26:55',
            'country' => 'United States',
            'latitude' => 40.75,
            'longitude' => -73.99,
            'timezone' => null,
        ]);

        $this->artisan('timezones:backfill')->assertSuccessful();

        $this->artisan('timezones:backfill')
            ->expectsOutputToContain('Check-ins given a venue timezone: 0')
            ->expectsOutputToContain('Instants derived: 0')
            ->assertSuccessful();
    });
});

/*
 * Without GPS, Strava names the first IANA zone matching the device's offset,
 * so an indoor workout in London comes back as Africa/Algiers in summer and
 * Africa/Abidjan in winter. The instant is right and the place is fiction.
 */
describe('Strava offset aliases', function () {
    it('empties a zone Strava guessed from the offset', function () {
        $guessed = Activity::factory()->create([
            'occurred_at' => '2024-07-02 18:00:00',
            'timezone' => 'Africa/Algiers',
            'track' => null,
        ]);

        $this->artisan('timezones:backfill')->assertSuccessful();

        expect($guessed->fresh()->timezone)->toBeNull();
    });

    it('keeps a zone backed by a GPS track, however foreign', function () {
        $abroad = Activity::factory()->create([
            'occurred_at' => '2022-10-16 07:46:35',
            'timezone' => 'America/New_York',
            'track' => [[40.75, -73.99]],
        ]);

        $this->artisan('timezones:backfill')->assertSuccessful();

        expect($abroad->fresh()->timezone)->toBe('America/New_York');
    });

    // The whole point of clearing them: a guessed zone is neither empty nor
    // home, so the trip pass would otherwise skip it forever.
    it('lets a flight place a workout whose zone was only ever a guess', function () {
        Flight::factory()->create(['occurred_at' => '2025-06-03 08:16:00', 'arrival_timezone' => 'Europe/Paris']);
        Flight::factory()->create(['occurred_at' => '2025-06-08 22:21:00', 'arrival_timezone' => 'Europe/London']);

        $workout = Activity::factory()->create([
            'occurred_at' => '2025-06-04 12:28:36',
            // UTC+2 is CEST, which is where he actually was.
            'timezone' => 'Africa/Blantyre',
            'track' => null,
        ]);

        $this->artisan('timezones:backfill')->assertSuccessful();

        expect($workout->fresh()->timezone)->toBe('Europe/Paris');
    });
});
