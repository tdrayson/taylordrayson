<?php

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Flight;
use App\Queries\FlightMapData;

// Airport/Airline are Sushi models whose getRows() returns [] in testing, so
// every code a flight references must be created explicitly. `place` is an
// appended accessor built from city + country (Airport.php:87), not a column,
// so set those two and expect a place of "London, GB".
beforeEach(function () {
    Airport::factory()->create(['iata_code' => 'LHR', 'city' => 'London', 'country' => 'GB', 'latitude' => 51.4700, 'longitude' => -0.4543]);
    Airport::factory()->create(['iata_code' => 'JFK', 'city' => 'New York', 'country' => 'US', 'latitude' => 40.6413, 'longitude' => -73.7781]);
    Airport::factory()->create(['iata_code' => 'CDG', 'city' => 'Paris', 'country' => 'FR', 'latitude' => 49.0097, 'longitude' => 2.5479]);
    Airline::factory()->create(['icao_code' => 'BAW', 'iata_code' => 'BA', 'name' => 'British Airways']);
});

it('buckets stats by year and all-time', function () {
    Flight::factory()->create([
        'occurred_at' => '2024-03-01 10:00:00',
        'origin_iata' => 'LHR', 'destination_iata' => 'JFK',
        'airline_icao' => 'BAW', 'distance' => 5_500_000, 'duration' => 28_800,
        'meta' => ['aircraft' => 'Boeing 777-300ER'],
    ]);
    Flight::factory()->create([
        'occurred_at' => '2023-06-01 10:00:00',
        'origin_iata' => 'LHR', 'destination_iata' => 'CDG',
        'airline_icao' => 'BAW', 'distance' => 350_000, 'duration' => 4_500,
        'meta' => ['aircraft' => 'Airbus A320'],
    ]);

    $payload = app(FlightMapData::class)();

    expect($payload['years'])->toBe([2024, 2023]);
    expect($payload['stats']['all']->flights)->toBe(2);
    expect($payload['stats']['all']->distance)->toBe(5_850_000);
    expect($payload['stats']['all']->duration)->toBe(33_300);
    expect($payload['stats']['all']->airports)->toBe(3);
    expect($payload['stats']['all']->airlines)->toBe(1);
    expect($payload['stats']['all']->longestDistance)->toBe(5_500_000);

    expect($payload['stats']['2024']->flights)->toBe(1);
    expect($payload['stats']['2023']->distance)->toBe(350_000);
});

it('drops flights whose airports have no coordinates', function () {
    Flight::factory()->create(['origin_iata' => 'LHR', 'destination_iata' => 'JFK', 'airline_icao' => 'BAW']);
    Flight::factory()->create(['origin_iata' => 'LHR', 'destination_iata' => 'ZZZ', 'airline_icao' => 'BAW']);

    $payload = app(FlightMapData::class)();

    expect($payload['entries'])->toHaveCount(1);
});

it('carries raw metres and seconds through to the entry', function () {
    Flight::factory()->create([
        'origin_iata' => 'LHR', 'destination_iata' => 'JFK', 'airline_icao' => 'BAW',
        'distance' => 5_500_000, 'duration' => 28_800,
    ]);

    $entry = app(FlightMapData::class)()['entries'][0];

    expect($entry->distance)->toBe(5_500_000);
    expect($entry->duration)->toBe(28_800);
    expect($entry->origin->lat)->toBe(51.47);
    expect($entry->airline->name)->toBe('British Airways');
});

/*
 * "Most flown" is a highlight, so it only earns its place when something
 * actually stands out. With 32 routes tied on two flights it was naming one of
 * them arbitrarily, and a sorted airport pair meant one return trip read as
 * having flown the same route twice.
 */
describe('most flown', function () {
    $flight = function (string $from, string $to, string $on): void {
        Flight::factory()->create([
            'occurred_at' => $on,
            'origin_iata' => $from, 'destination_iata' => $to,
            'airline_icao' => 'BAW', 'distance' => 1_000_000, 'duration' => 3_600,
            'meta' => ['aircraft' => 'Airbus A320'],
        ]);
    };

    it('does not count a return trip as flying the same route twice', function () use ($flight) {
        $flight('LHR', 'JFK', '2024-03-01 10:00:00');
        $flight('JFK', 'LHR', '2024-03-08 10:00:00');

        $stats = app(FlightMapData::class)()['stats']['all']->toArray();

        expect($stats['topRoute'])->toBeNull()
            ->and($stats['topRouteCount'])->toBe(0);
    });

    it('names a route only once it beats every other', function () use ($flight) {
        $flight('LHR', 'JFK', '2024-03-01 10:00:00');
        $flight('LHR', 'JFK', '2024-04-01 10:00:00');
        $flight('LHR', 'CDG', '2024-05-01 10:00:00');

        $stats = app(FlightMapData::class)()['stats']['all']->toArray();

        expect($stats['topRoute'])->toBe('LHR to JFK')
            ->and($stats['topRouteCount'])->toBe(2);
    });

    it('says nothing when two routes tie at the top', function () use ($flight) {
        $flight('LHR', 'JFK', '2024-03-01 10:00:00');
        $flight('LHR', 'JFK', '2024-04-01 10:00:00');
        $flight('LHR', 'CDG', '2024-05-01 10:00:00');
        $flight('LHR', 'CDG', '2024-06-01 10:00:00');

        expect(app(FlightMapData::class)()['stats']['all']->toArray()['topRoute'])->toBeNull();
    });
});
