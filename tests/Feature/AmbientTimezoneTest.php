<?php

use App\Models\Calorie;
use App\Models\Flight;
use App\Models\Sleep;
use App\Support\StateStore;
use Carbon\CarbonImmutable;

/*
 * Sleep, food, fuel and podcasts carry no location, so the phone's last reading
 * is the only thing that can place them. It is trusted only for entries
 * arriving in real time: no history of readings is kept, so today's location
 * says nothing about where a row being imported from 2013 happened.
 */

function reportLocation(string $timezone, ?CarbonImmutable $observedAt = null): void
{
    app(StateStore::class)->put(
        'now.location',
        ['city' => 'Madrid', 'timezone' => $timezone],
        $observedAt ?? CarbonImmutable::now(),
    );
}

it('stamps a live entry with wherever the phone last reported from', function () {
    reportLocation('Europe/Madrid');

    $sleep = Sleep::factory()->create(['occurred_at' => now()->subHours(8), 'timezone' => null]);

    expect($sleep->fresh()->timezone)->toBe('Europe/Madrid');
});

it('leaves an entry that already knows its own zone alone', function () {
    reportLocation('Europe/Madrid');

    $sleep = Sleep::factory()->create(['occurred_at' => now()->subHours(8), 'timezone' => 'America/New_York']);

    expect($sleep->fresh()->timezone)->toBe('America/New_York');
});

// The guard that keeps a bulk import of history from being stamped with today.
it('ignores an entry too old to have arrived in real time', function () {
    reportLocation('Europe/Madrid');

    $sleep = Sleep::factory()->create(['occurred_at' => now()->subDays(30), 'timezone' => null]);

    expect($sleep->fresh()->timezone)->toBeNull();
});

it('ignores a reading too old to say where the phone is', function () {
    reportLocation('Europe/Madrid', CarbonImmutable::now()->subDays(5));

    $sleep = Sleep::factory()->create(['occurred_at' => now()->subHours(8), 'timezone' => null]);

    expect($sleep->fresh()->timezone)->toBeNull();
});

// Empty already means home, so writing it would only hide which rows were
// ever positively placed.
it('leaves the column empty when the phone is at home', function () {
    reportLocation('Europe/London');

    $sleep = Sleep::factory()->create(['occurred_at' => now()->subHours(8), 'timezone' => null]);

    expect($sleep->fresh()->timezone)->toBeNull();
});

it('does nothing when the phone has never reported', function () {
    $sleep = Sleep::factory()->create(['occurred_at' => now()->subHours(8), 'timezone' => null]);

    expect($sleep->fresh()->timezone)->toBeNull();
});

// Flight has no timezone column: it keeps a departure and an arrival zone.
it('skips a model that has no timezone of its own', function () {
    reportLocation('Europe/Madrid');

    $flight = Flight::factory()->create(['occurred_at' => now()->subHours(2)]);

    expect($flight->fresh())->not->toBeNull();
});

it('derives the instant from the zone it just stamped', function () {
    reportLocation('Europe/Madrid');

    $occurredAt = now()->subHours(8)->startOfMinute();
    $sleep = Sleep::factory()->create(['occurred_at' => $occurredAt, 'timezone' => null]);

    // The same wall clock read in Madrid rather than in London, so the spine
    // row orders by when it happened.
    expect($sleep->fresh()->timelineEntry->occurred_utc->toDateTimeString())
        ->toBe(CarbonImmutable::parse($occurredAt->format('Y-m-d H:i:s'), 'Europe/Madrid')->utc()->toDateTimeString());
});

it('gives a food day an instant, which its observer never used to write', function () {
    $calorie = Calorie::factory()->create(['occurred_at' => '2026-07-01 09:00:00', 'timezone' => 'America/New_York']);

    $entry = $calorie->fresh()->timelineEntry;

    // End of 1 July in New York, which is four hours into 2 July in UTC.
    expect($entry->occurred_utc->toDateTimeString())->toBe('2026-07-02 03:59:59');
});
