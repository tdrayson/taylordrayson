<?php

use App\Models\Fuel;
use App\Models\User;

it('draws every primary field as an input, not a chip', function () {
    $this->actingAs(User::factory()->create());

    // Asserting the rendered DOM, not the Inertia props: a chip would satisfy
    // a text assertion while the field was still collapsed.
    visit('/new/fuel')
        ->assertPresent('#cost')
        ->assertPresent('#price_per_litre')
        ->assertPresent('#occurred_at')
        ->assertPresent('#vehicle_id')
        ->assertNoJavascriptErrors();
});

it('shows a map for a location that has resolved coordinates', function () {
    $this->actingAs(User::factory()->create());

    $fuel = Fuel::factory()->create([
        'occurred_at' => '2026-08-13 12:00:00',
        'station_name' => 'Beddington Lane Service Station',
        'latitude' => 51.3835,
        'longitude' => -0.1285,
    ]);

    visit($fuel->url().'?edit')
        ->assertPresent('canvas.maplibregl-canvas')
        ->assertNoJavascriptErrors();
});
