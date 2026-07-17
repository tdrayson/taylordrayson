<?php

use App\Actions\GenerateFlightMap;
use App\Models\Airport;
use App\Models\Flight;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['services.mapbox.token' => 'test-token']);
    Storage::fake('public');
});

it('stores light and dark arc maps from the flight endpoints', function () {
    Http::fake(['*api.mapbox.com*' => Http::response('PNGDATA', 200)]);

    // The Airport table is empty in tests until seeded, so pin the flight to a
    // known LHR/JFK pair with explicit coordinates rather than relying on
    // FlightFactory's random IATA codes resolving via the origin/destination
    // belongsTo relations.
    Airport::factory()->create(['iata_code' => 'LHR', 'latitude' => 51.4700, 'longitude' => -0.4543]);
    Airport::factory()->create(['iata_code' => 'JFK', 'latitude' => 40.6413, 'longitude' => -73.7781]);

    $flight = Flight::factory()->create(['origin_iata' => 'LHR', 'destination_iata' => 'JFK']);

    app(GenerateFlightMap::class)($flight->load(['origin', 'destination']));

    expect($flight->getFirstMediaUrl('map'))->not->toBe('');
    expect($flight->getFirstMediaUrl('map_dark'))->not->toBe('');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'light-v11'));
    Http::assertSent(fn ($request) => str_contains($request->url(), 'dark-v11'));
});
