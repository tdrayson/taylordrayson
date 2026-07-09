<?php

use App\Models\Airport;
use App\Models\Flight;
use App\Stories\FlightStory;
use App\Support\Distance;

it('reports no data when there are no flights', function () {
    expect(app(FlightStory::class)->build())->toBe(['hasData' => false]);
});

it('rolls up kpis, countries and the domestic split from flights', function () {
    Airport::create(['iata_code' => 'LGW', 'name' => 'Gatwick', 'city' => 'London', 'country' => 'GB']);
    Airport::create(['iata_code' => 'BFS', 'name' => 'Belfast', 'city' => 'Belfast', 'country' => 'GB']);
    Airport::create(['iata_code' => 'OPO', 'name' => 'Porto', 'city' => 'Porto', 'country' => 'PT']);

    // A domestic hop, plus an international out-and-back through the same hub.
    Flight::factory()->create(['occurred_at' => '2023-11-20 09:00:00', 'origin_iata' => 'LGW', 'destination_iata' => 'BFS', 'distance' => Distance::fromMiles(320), 'duration' => 4500, 'reason' => 'personal']);
    Flight::factory()->create(['occurred_at' => '2022-06-02 09:00:00', 'origin_iata' => 'LGW', 'destination_iata' => 'OPO', 'distance' => Distance::fromMiles(800), 'duration' => 8000, 'reason' => 'business']);
    Flight::factory()->create(['occurred_at' => '2022-06-06 09:00:00', 'origin_iata' => 'OPO', 'destination_iata' => 'LGW', 'distance' => Distance::fromMiles(800), 'duration' => 8000, 'reason' => 'business']);

    $story = app(FlightStory::class)->build();

    expect($story['hasData'])->toBeTrue()
        ->and($story['kpis']['flights'])->toBe(3)
        ->and($story['kpis']['miles'])->toBe(1920)
        ->and($story['kpis']['countries'])->toBe(2)
        ->and($story['kpis']['airports'])->toBe(3)
        ->and($story['kpis']['domestic'])->toBe(1)
        ->and($story['kpis']['international'])->toBe(2)
        // 20,500 seconds = 5.69 hours.
        ->and($story['kpis']['hours'])->toBe(5.7);
});

it('finds the longest and shortest legs and the home hub', function () {
    Flight::factory()->create(['occurred_at' => '2022-02-21 09:00:00', 'origin_iata' => 'LHR', 'destination_iata' => 'LAS', 'distance' => Distance::fromMiles(5216)]);
    Flight::factory()->create(['occurred_at' => '2022-02-28 09:00:00', 'origin_iata' => 'DFW', 'destination_iata' => 'AUS', 'distance' => Distance::fromMiles(191)]);
    Flight::factory()->create(['occurred_at' => '2023-05-15 09:00:00', 'origin_iata' => 'LGW', 'destination_iata' => 'MXP', 'distance' => Distance::fromMiles(600)]);
    Flight::factory()->create(['occurred_at' => '2023-05-17 09:00:00', 'origin_iata' => 'MXP', 'destination_iata' => 'LGW', 'distance' => Distance::fromMiles(600)]);

    $story = app(FlightStory::class)->build();

    expect($story['extremes']['longest']['route'])->toBe('LHR to LAS')
        ->and($story['extremes']['longest']['miles'])->toBe(5216)
        ->and($story['extremes']['shortest']['route'])->toBe('DFW to AUS')
        ->and($story['extremes']['shortest']['miles'])->toBe(191)
        ->and($story['hub']['code'])->toBe('LGW')
        ->and($story['hub']['flights'])->toBe(2);
});

it('splits seats and counts the budget-airline share', function () {
    Flight::factory()->create(['airline_icao' => 'EZY', 'meta' => ['seat_type' => 'WINDOW']]);
    Flight::factory()->create(['airline_icao' => 'EZY', 'meta' => ['seat_type' => 'WINDOW']]);
    Flight::factory()->create(['airline_icao' => 'EZY', 'meta' => ['seat_type' => 'AISLE']]);
    Flight::factory()->create(['airline_icao' => 'BAW', 'meta' => ['seat_type' => 'MIDDLE']]);

    $story = app(FlightStory::class)->build();

    expect($story['seats']['known'])->toBe(4)
        ->and($story['seats']['window'])->toBe(2)
        ->and($story['seats']['windowPct'])->toBe(50)
        ->and($story['budget']['easyjet'])->toBe(3)
        ->and($story['budget']['budgetPct'])->toBe(75);
});

it('counts flights and miles per year with the empty years filled', function () {
    Flight::factory()->create(['occurred_at' => '2020-08-01 09:00:00', 'distance' => Distance::fromMiles(3000)]);
    Flight::factory()->create(['occurred_at' => '2022-06-01 09:00:00', 'distance' => Distance::fromMiles(800)]);
    Flight::factory()->create(['occurred_at' => '2022-06-05 09:00:00', 'distance' => Distance::fromMiles(800)]);

    $byYear = collect(app(FlightStory::class)->build()['byYear'])->keyBy('year');

    expect($byYear[2020]['flights'])->toBe(1)
        ->and($byYear[2021]['flights'])->toBe(0)
        ->and($byYear[2022]['flights'])->toBe(2)
        ->and($byYear[2022]['miles'])->toBe(1600);
});
