<?php

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Flight;

beforeEach(function () {
    config()->set('services.api.token', 'test-token');

    Airline::factory()->create(['icao_code' => 'BAW', 'iata_code' => 'BA', 'name' => 'British Airways']);
    Airport::factory()->create(['iata_code' => 'LGW', 'name' => 'London Gatwick']);
    Airport::factory()->create(['iata_code' => 'MAD', 'name' => 'Madrid Barajas']);
});

function validFlightPayload(): array
{
    return [
        'occurred_at' => '2026-08-12 10:35:00',
        'flight_number' => '2718',
        'airline_icao' => 'BAW',
        'origin_iata' => 'LGW',
        'destination_iata' => 'MAD',
        'duration' => 8700,
        'distance' => 1245632,
        'cabin_class' => 'economy',
        'reason' => 'holiday',
        'departure_timezone' => 'Europe/London',
        'arrival_timezone' => 'Europe/Madrid',
    ];
}

it('creates a flight with airline and airports resolved', function () {
    $this->withToken('test-token')->postJson('/api/v1/flights', validFlightPayload())
        ->assertCreated()
        ->assertJsonPath('data.origin.iata', 'LGW')
        ->assertJsonPath('data.airline.name', 'British Airways')
        ->assertJsonPath('data.distance', 1245632);

    expect(Flight::count())->toBe(1)
        ->and(Flight::first()->timelineEntry)->not->toBeNull();
});

it('accepts human-friendly duration and distance units', function () {
    $payload = array_merge(validFlightPayload(), [
        'duration' => '2h 25m',
        'distance' => '774 miles',
    ]);

    $this->withToken('test-token')->postJson('/api/v1/flights', $payload)
        ->assertCreated()
        ->assertJsonPath('data.duration', 8700)
        ->assertJsonPath('data.distance', 1245632);
});

it('rejects unparseable unit strings', function () {
    $this->withToken('test-token')
        ->postJson('/api/v1/flights', array_merge(validFlightPayload(), ['duration' => 'a while']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['duration']);
});

it('is idempotent on the natural key', function () {
    $this->withToken('test-token')->postJson('/api/v1/flights', validFlightPayload())->assertCreated();
    $this->withToken('test-token')
        ->postJson('/api/v1/flights', array_merge(validFlightPayload(), ['distance' => 1245700]))
        ->assertOk();

    expect(Flight::count())->toBe(1)
        ->and(Flight::first()->distance)->toBe(1245700);
});

it('is idempotent when the retry uses a different timestamp format', function () {
    $this->withToken('test-token')->postJson('/api/v1/flights', validFlightPayload())->assertCreated();

    $retry = array_merge(validFlightPayload(), ['occurred_at' => '2026-08-12T10:35:00+01:00']);
    $this->withToken('test-token')->postJson('/api/v1/flights', $retry)->assertOk();

    expect(Flight::count())->toBe(1);
});

it('rejects unknown airports and airlines', function () {
    $this->withToken('test-token')
        ->postJson('/api/v1/flights', array_merge(validFlightPayload(), ['origin_iata' => 'XXX']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['origin_iata']);

    $this->withToken('test-token')
        ->postJson('/api/v1/flights', array_merge(validFlightPayload(), ['airline_icao' => 'ZZZ']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['airline_icao']);
});

it('rejects unauthenticated writes', function () {
    $this->postJson('/api/v1/flights', validFlightPayload())->assertUnauthorized();

    expect(Flight::count())->toBe(0);
});

it('updates and deletes a flight', function () {
    $flight = Flight::factory()->create(['cabin_class' => 'economy', 'origin_iata' => 'LGW', 'destination_iata' => 'MAD', 'airline_icao' => 'BAW']);

    $this->withToken('test-token')->patchJson("/api/v1/flights/{$flight->id}", ['cabin_class' => 'business'])
        ->assertOk()
        ->assertJsonPath('data.cabin_class', 'business');

    $this->withToken('test-token')->deleteJson("/api/v1/flights/{$flight->id}")->assertNoContent();

    expect(Flight::count())->toBe(0);
});

it('defaults both timezones to the home timezone when omitted', function () {
    $payload = validFlightPayload();
    unset($payload['departure_timezone'], $payload['arrival_timezone']);

    $this->withToken('test-token')->postJson('/api/v1/flights', $payload)
        ->assertCreated()
        ->assertJsonPath('data.departure_timezone', 'Europe/London')
        ->assertJsonPath('data.arrival_timezone', 'Europe/London');
});

it('rejects a cabin_class outside the CabinClass enum', function () {
    $this->withToken('test-token')
        ->postJson('/api/v1/flights', array_merge(validFlightPayload(), ['cabin_class' => 'spaceship']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cabin_class']);
});

it('accepts premium_economy as a valid cabin_class', function () {
    $this->withToken('test-token')
        ->postJson('/api/v1/flights', array_merge(validFlightPayload(), ['cabin_class' => 'premium_economy']))
        ->assertCreated()
        ->assertJsonPath('data.cabin_class', 'premium_economy');
});

it('accepts a null cabin_class', function () {
    $payload = validFlightPayload();
    unset($payload['cabin_class']);

    $this->withToken('test-token')->postJson('/api/v1/flights', $payload)
        ->assertCreated()
        ->assertJsonPath('data.cabin_class', null);
});
