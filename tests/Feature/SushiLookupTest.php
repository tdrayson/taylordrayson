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
