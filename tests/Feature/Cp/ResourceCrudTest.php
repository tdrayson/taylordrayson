<?php

use App\Models\Flight;
use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('lists records for a resource', function () {
    Flight::factory()->count(3)->create();

    $this->get('/cp/flights')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Cp/Resource/Index')
            ->where('resource.slug', 'flights')
            ->has('records.data', 3));
});

it('shows the create form', function () {
    $this->get('/cp/flights/create')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Cp/Resource/Form')
            ->where('record', null)
            ->has('resource.fields'));
});

it('stores a new record', function () {
    $this->post('/cp/flights', [
        'occurred_at' => '2026-07-01T09:30',
        'flight_number' => 'BA112',
        'origin_iata' => 'LHR',
        'destination_iata' => 'JFK',
        'cabin_class' => 'business',
        'airline_icao' => 'BAW',
    ])->assertRedirect('/cp/flights');

    expect(Flight::where('flight_number', 'BA112')->exists())->toBeTrue();
});

it('updates a record', function () {
    $flight = Flight::factory()->create(['flight_number' => 'OLD']);

    $this->put("/cp/flights/{$flight->id}", [
        'occurred_at' => $flight->occurred_at->format('Y-m-d\TH:i'),
        'flight_number' => 'NEW123',
        'airline_icao' => $flight->airline_icao,
        'origin_iata' => $flight->origin_iata,
        'destination_iata' => $flight->destination_iata,
    ])->assertRedirect('/cp/flights');

    expect($flight->fresh()->flight_number)->toBe('NEW123');
});

it('deletes a record', function () {
    $flight = Flight::factory()->create();

    $this->delete("/cp/flights/{$flight->id}")->assertRedirect('/cp/flights');

    expect(Flight::find($flight->id))->toBeNull();
});

it('falls back to the default sort when given an unknown sort column', function () {
    Flight::factory()->count(2)->create();

    $this->get('/cp/flights?sort=bogus_column')->assertSuccessful();
});

it('returns 404 for an unknown resource', function () {
    $this->get('/cp/nonsense')->assertNotFound();
});

it('blocks guests from resource routes', function () {
    auth()->logout();

    $this->get('/cp/flights')->assertRedirect(route('cp.login'));
});
