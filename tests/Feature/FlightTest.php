<?php

use App\Models\Airline;
use App\Models\Flight;

it('computes the landed time in the destination local timezone, DST-aware', function () {
    // BST departure (UTC+1) → EDT arrival (UTC-4): 09:30 + 7h25m = 11:55 New York.
    $summer = new Flight([
        'occurred_at' => '2026-07-01 09:30:00',
        'duration' => 26700,
        'departure_timezone' => 'Europe/London',
        'arrival_timezone' => 'America/New_York',
        'meta' => [],
    ]);

    expect($summer->departed_local)->toBe('2026-07-01T09:30');
    expect($summer->arrived_local)->toBe('2026-07-01T11:55');
});

it('prefers an explicit arrival time from meta over the computed one', function () {
    $flight = new Flight([
        'occurred_at' => '2026-07-01 09:30:00',
        'duration' => 26700,
        'departure_timezone' => 'Europe/London',
        'arrival_timezone' => 'America/New_York',
        'meta' => ['arrived_actual' => '2026-07-01T12:10'],
    ]);

    expect($flight->arrived_local)->toBe('2026-07-01T12:10');
});

it('returns a null arrival when the flight is not yet enriched', function () {
    $flight = new Flight(['occurred_at' => '2026-07-01 09:30:00', 'meta' => []]);

    expect($flight->departed_local)->toBe('2026-07-01T09:30');
    expect($flight->arrived_local)->toBeNull();
});

it('includes the flight designator (iata code + number) in the card airline', function () {
    $flight = new Flight(['airline_icao' => 'EZY', 'flight_number' => '8821', 'occurred_at' => '2026-06-03 17:20:00']);
    $flight->setRelation('airline', new Airline(['icao_code' => 'EZY', 'iata_code' => 'U2', 'name' => 'easyJet UK']));

    $airline = $flight->card()['meta']['route']['airline'];

    expect($airline['number'])->toBe('U2 8821');
    expect($airline['name'])->toBe('easyJet UK');
});
