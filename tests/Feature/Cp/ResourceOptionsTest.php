<?php

use App\Models\Airline;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    actingAs(User::factory()->create());
});

it('returns value/label options for a relation field', function () {
    Airline::factory()->create(['icao_code' => 'EZY', 'iata_code' => 'U2', 'name' => 'easyJet UK']);
    Airline::factory()->create(['icao_code' => 'RYR', 'iata_code' => 'FR', 'name' => 'Ryanair']);

    $response = $this->getJson('/cp/flights/options?field=airline_icao&q=easy');

    $response->assertSuccessful();
    $response->assertJsonPath('options.0.value', 'EZY');
    $response->assertJsonPath('options.0.label', 'easyJet UK');
    expect($response->json('options'))->toHaveCount(1);
});

it('always includes the current value even when it does not match the query', function () {
    Airline::factory()->create(['icao_code' => 'EZY', 'iata_code' => 'U2', 'name' => 'easyJet UK']);

    $response = $this->getJson('/cp/flights/options?field=airline_icao&q=zzzzz&value=EZY');

    $response->assertSuccessful();
    expect(collect($response->json('options'))->pluck('value'))->toContain('EZY');
});

it('rejects a field that is not a declared relation', function () {
    $this->getJson('/cp/flights/options?field=flight_number')->assertStatus(422);
});
