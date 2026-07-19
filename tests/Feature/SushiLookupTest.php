<?php

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Flight;
use App\Rules\ExistsOnModel;
use App\Support\LookupCsv;

it('loads the real airline and airport rows from the canonical csvs', function () {
    $airlines = LookupCsv::from(database_path('lookups/airlines.csv'));
    $airports = LookupCsv::from(database_path('lookups/airports.csv'));

    expect(count($airlines))->toBeGreaterThan(5000)
        ->and(count($airports))->toBeGreaterThan(9000)
        ->and(collect($airlines)->firstWhere('icao_code', 'BAW')['name'] ?? null)->toBe('British Airways')
        ->and(collect($airports)->firstWhere('iata_code', 'LHR')['city'] ?? null)->toBe('London');
});

/**
 * Regression: Sushi's $schema declares column types only and creates no unique
 * indexes, unlike the dropped `airlines_icao_code_unique` / `airports_iata_code_unique`
 * database constraints. If a future CSV edit ever introduces a duplicate key, nothing
 * would catch it: Eloquent's belongsTo dictionary is last-one-wins, so a flight would
 * silently resolve to the WRONG airline or airport with no error. This test is the
 * only guard against that, since it reads the real CSVs directly.
 */
it('has no duplicate icao_code or iata_code keys in the canonical csvs', function () {
    $airlines = collect(LookupCsv::from(database_path('lookups/airlines.csv')));
    $airports = collect(LookupCsv::from(database_path('lookups/airports.csv')));

    $airlineIcaoCodes = $airlines->pluck('icao_code')->filter();
    $airportIataCodes = $airports->pluck('iata_code')->filter();

    expect($airlineIcaoCodes->count())->toBe(
        $airlineIcaoCodes->unique()->count(),
        'Duplicate airline icao_code values would make flights resolve to the wrong airline (belongsTo is last-one-wins).'
    )->and($airportIataCodes->count())->toBe(
        $airportIataCodes->unique()->count(),
        'Duplicate airport iata_code values would make flights resolve to the wrong airport (belongsTo is last-one-wins).'
    );
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

/**
 * Regression: ExistsOnModel must humanize the attribute name in its error message
 * the same way Laravel's built-in `exists:` rule does. A previous implementation
 * interpolated the raw attribute name (e.g. "airline_icao"), producing a message
 * that read differently from every other field's validation error in the same
 * response. Asserting only the error key would not have caught this.
 */
it('humanizes the attribute name in the exists on model error message', function () {
    $validator = validator(
        ['airline_icao' => 'ZZZ'],
        ['airline_icao' => [new ExistsOnModel(Airline::class, 'icao_code')]],
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('airline_icao'))->toBe('The selected airline icao is invalid.');
});
