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
