<?php

use App\Models\Flight;
use App\Models\Sleep;
use App\Support\StateStore;
use Carbon\CarbonImmutable;

/*
 * Where the phone cannot say, flights can. A flight is an explicit "I changed
 * zone at this moment", and unlike the phone's last reading it still answers
 * for an entry arriving days late or imported from years ago.
 */

function flightBetween(string $departure, string $arrival, CarbonImmutable $departedAt, int $minutes = 90): Flight
{
    return Flight::factory()->create([
        'occurred_at' => $departedAt->format('Y-m-d H:i:s'),
        'departure_timezone' => $departure,
        'arrival_timezone' => $arrival,
        'duration' => $minutes * 60,
        'meta' => [],
    ]);
}

function phoneAt(string $timezone): void
{
    app(StateStore::class)->put('now.location', ['city' => 'Somewhere', 'timezone' => $timezone], CarbonImmutable::now());
}

function sleepAt(CarbonImmutable $at): Sleep
{
    return Sleep::factory()->create(['occurred_at' => $at->format('Y-m-d H:i:s'), 'timezone' => null]);
}

it('places an entry from a past trip by the flights either side of it', function () {
    flightBetween('Europe/London', 'Europe/Zurich', CarbonImmutable::parse('2026-03-02 09:00'));
    flightBetween('Europe/Zurich', 'Europe/London', CarbonImmutable::parse('2026-03-09 18:00'));

    // Far too old for the phone's last reading to describe, which is exactly
    // the gap flights fill: Rovi backfills a week, and imports go back years.
    $sleep = sleepAt(CarbonImmutable::parse('2026-03-05 07:00'));

    expect($sleep->fresh()->timezone)->toBe('Europe/Zurich');
});

it('places an entry while the trip is still running', function () {
    flightBetween('Europe/London', 'Europe/Zurich', CarbonImmutable::now()->subDays(2));

    expect(sleepAt(CarbonImmutable::now()->subHours(8))->fresh()->timezone)->toBe('Europe/Zurich');
});

it('leaves an entry outside any trip empty, which already means home', function () {
    flightBetween('Europe/London', 'Europe/Zurich', CarbonImmutable::parse('2026-03-02 09:00'));
    flightBetween('Europe/Zurich', 'Europe/London', CarbonImmutable::parse('2026-03-09 18:00'));

    expect(sleepAt(CarbonImmutable::parse('2026-04-20 07:00'))->fresh()->timezone)->toBeNull();
});

describe('a phone that has not caught up', function () {
    it('takes the arrival zone when the phone still names the airport left from', function () {
        phoneAt('Europe/Zurich');
        flightBetween('Europe/Zurich', 'Europe/Paris', CarbonImmutable::now()->subHours(4));

        expect(sleepAt(CarbonImmutable::now()->subHour())->fresh()->timezone)->toBe('Europe/Paris');
    });

    // Flying out and coming back overland: the phone is right, and without a
    // bound the rule would keep overruling it with the destination.
    it('keeps the phone once a day has passed since landing', function () {
        phoneAt('Europe/Zurich');
        flightBetween('Europe/Zurich', 'Europe/Paris', CarbonImmutable::now()->subHours(30));

        expect(sleepAt(CarbonImmutable::now()->subHour())->fresh()->timezone)->toBe('Europe/Zurich');
    });

    // Driving on from where you landed: a third zone is movement the phone
    // detected, not lag.
    it('keeps the phone when it names a zone no flight explains', function () {
        phoneAt('Europe/Amsterdam');
        flightBetween('Europe/London', 'Europe/Paris', CarbonImmutable::now()->subHours(4));

        expect(sleepAt(CarbonImmutable::now()->subHour())->fresh()->timezone)->toBe('Europe/Amsterdam');
    });

    it('keeps the phone when it already agrees with where the flight landed', function () {
        phoneAt('Europe/Paris');
        flightBetween('Europe/London', 'Europe/Paris', CarbonImmutable::now()->subHours(4));

        expect(sleepAt(CarbonImmutable::now()->subHour())->fresh()->timezone)->toBe('Europe/Paris');
    });
});

it('will not stretch a trip past the cap when a return was never recorded', function () {
    flightBetween('Europe/London', 'Atlantic/Canary', CarbonImmutable::parse('2012-08-01 09:00'));
    flightBetween('Europe/London', 'Europe/Zurich', CarbonImmutable::parse('2014-04-01 09:00'));
    flightBetween('Europe/Zurich', 'Europe/London', CarbonImmutable::parse('2014-04-08 09:00'));

    // Twenty months between the outbound and the next flight, so the span is
    // fiction and is dropped rather than stamping two years of London evenings.
    expect(sleepAt(CarbonImmutable::parse('2013-02-01 07:00'))->fresh()->timezone)->toBeNull();
});
