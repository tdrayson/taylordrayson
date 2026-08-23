<?php

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Flight;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the flights map page', function () {
    Airport::factory()->create(['iata_code' => 'LHR', 'city' => 'London', 'country' => 'GB', 'latitude' => 51.4700, 'longitude' => -0.4543]);
    Airport::factory()->create(['iata_code' => 'JFK', 'city' => 'New York', 'country' => 'US', 'latitude' => 40.6413, 'longitude' => -73.7781]);
    Airline::factory()->create(['icao_code' => 'BAW', 'iata_code' => 'BA', 'name' => 'British Airways']);

    Flight::factory()->create([
        'occurred_at' => '2024-03-01 10:00:00',
        'origin_iata' => 'LHR', 'destination_iata' => 'JFK', 'airline_icao' => 'BAW',
        'distance' => 5_500_000, 'duration' => 28_800,
    ]);

    $this->get('/flights/map')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Flights/Map')
            ->has('entries', 1)
            ->where('entries.0.distance', 5_500_000)
            ->has('stats.all')
            ->where('years', [2024])
        );
});
