<?php

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Flight;
use App\Support\LookupCsv;

it('loads the real airline and airport rows from the canonical csvs', function () {
    $airlines = LookupCsv::from(database_path('lookups/airlines.csv'));
    $airports = LookupCsv::from(database_path('lookups/airports.csv'));

    expect($airlines)->toHaveCount(5842)
        ->and($airports)->toHaveCount(9070)
        ->and(collect($airlines)->firstWhere('icao_code', 'BAW')['name'] ?? null)->toBe('British Airways')
        ->and(collect($airports)->firstWhere('iata_code', 'LHR')['city'] ?? null)->toBe('London');
});

it('starts with empty lookup tables under test', function () {
    expect(Airline::count())->toBe(0)
        ->and(Airport::count())->toBe(0);
});

it('resolves flight airline and airport relations across the sushi connection', function () {
    Airline::factory()->create(['icao_code' => 'BAW', 'iata_code' => 'BA', 'name' => 'British Airways']);
    Airport::factory()->create(['iata_code' => 'LHR', 'name' => 'Heathrow', 'city' => 'London', 'country' => 'GB']);
    Airport::factory()->create(['iata_code' => 'JFK', 'name' => 'JFK', 'city' => 'New York', 'country' => 'US']);

    $flight = Flight::factory()->create([
        'airline_icao' => 'BAW',
        'origin_iata' => 'LHR',
        'destination_iata' => 'JFK',
    ]);

    $flight->load(['airline', 'origin', 'destination']);

    expect($flight->airline?->name)->toBe('British Airways')
        ->and($flight->origin?->iata_code)->toBe('LHR')
        ->and($flight->destination?->city)->toBe('New York')
        ->and($flight->origin?->place)->toBe('London, GB');
});

/**
 * Regression: Airline model must NOT cache under test. A cached empty row set
 * would poison the shared Sushi cache file, causing later dev requests to serve
 * no airlines even when the CSV is unchanged.
 */
it('disables airline caching under test to prevent cache poisoning', function () {
    $airline = new Airline;
    $reflection = new ReflectionMethod($airline, 'sushiShouldCache');
    $reflection->setAccessible(true);

    expect($reflection->invoke($airline))->toBeFalse('Airline must not cache in test environment')
        ->and($airline->getRows())->toBe([], 'Airline must return empty rows in test environment');
});

/**
 * Regression: Airport model must NOT cache under test. A cached empty row set
 * would poison the shared Sushi cache file, causing later dev requests to serve
 * no airports even when the CSV is unchanged.
 */
it('disables airport caching under test to prevent cache poisoning', function () {
    $airport = new Airport;
    $reflection = new ReflectionMethod($airport, 'sushiShouldCache');
    $reflection->setAccessible(true);

    expect($reflection->invoke($airport))->toBeFalse('Airport must not cache in test environment')
        ->and($airport->getRows())->toBe([], 'Airport must return empty rows in test environment');
});
