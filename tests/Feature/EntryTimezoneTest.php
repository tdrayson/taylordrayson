<?php

use App\Models\Activity;
use App\Models\Checkin;
use App\Models\Flight;
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
