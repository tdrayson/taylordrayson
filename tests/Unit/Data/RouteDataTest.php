<?php

use App\Data\AirlineData;
use App\Data\RouteData;
use App\Data\RoutePoint;

it('serialises a full route with both endpoints and an airline', function () {
    $route = new RouteData(
        origin: new RoutePoint('LHR', 'London, GB', 'Heathrow', 51.4700, -0.4543),
        destination: new RoutePoint('JFK', 'New York, US', 'JFK', 40.6413, -73.7781),
        depart: '2026-07-01T09:30',
        arrive: '2026-07-01T11:55',
        distance: 3451,
        duration: 26700,
        airline: new AirlineData('easyJet UK', '/logos/airlines/icon/U2.png', 'U2 8821'),
    );

    expect($route->toArray())->toBe([
        'origin' => ['iata' => 'LHR', 'place' => 'London, GB', 'name' => 'Heathrow', 'lat' => 51.4700, 'lng' => -0.4543],
        'destination' => ['iata' => 'JFK', 'place' => 'New York, US', 'name' => 'JFK', 'lat' => 40.6413, 'lng' => -73.7781],
        'depart' => '2026-07-01T09:30',
        'arrive' => '2026-07-01T11:55',
        'distance' => 3451,
        'duration' => 26700,
        'airline' => ['name' => 'easyJet UK', 'icon' => '/logos/airlines/icon/U2.png', 'number' => 'U2 8821'],
    ]);
});

it('serialises a route with no relations loaded and no airline', function () {
    $route = new RouteData(
        origin: new RoutePoint('LHR', null, null, null, null),
        destination: new RoutePoint('JFK', null, null, null, null),
        depart: null,
        arrive: null,
        distance: null,
        duration: null,
        airline: null,
    );

    expect($route->toArray())->toBe([
        'origin' => ['iata' => 'LHR', 'place' => null, 'name' => null, 'lat' => null, 'lng' => null],
        'destination' => ['iata' => 'JFK', 'place' => null, 'name' => null, 'lat' => null, 'lng' => null],
        'depart' => null,
        'arrive' => null,
        'distance' => null,
        'duration' => null,
        'airline' => null,
    ]);
});
