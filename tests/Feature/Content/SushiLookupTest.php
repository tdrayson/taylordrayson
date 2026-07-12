<?php

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Flight;
use App\Support\CsvLookupRows;

it('loads airline and airport rows from the csv lookups outside testing', function () {
    $airlines = CsvLookupRows::from(base_path('data/airlines.csv'));
    $airports = CsvLookupRows::from(base_path('data/airports.csv'));

    expect($airlines)->not->toBeEmpty()
        ->and($airports)->not->toBeEmpty()
        ->and(collect($airlines)->firstWhere('icao_code', 'BAW')['name'] ?? null)->toContain('British')
        ->and(collect($airports)->firstWhere('iata_code', 'LHR')['city'] ?? null)->toBe('London');
});

it('resolves flight airline and airport relations from sushi models', function () {
    Airline::factory()->create([
        'icao_code' => 'BAW',
        'iata_code' => 'BA',
        'name' => 'British Airways',
    ]);

    Airport::factory()->create([
        'iata_code' => 'LHR',
        'name' => 'Heathrow',
        'city' => 'London',
        'country' => 'GB',
    ]);

    Airport::factory()->create([
        'iata_code' => 'JFK',
        'name' => 'JFK',
        'city' => 'New York',
        'country' => 'US',
    ]);

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
