<?php

use App\Models\Airport;
use App\Models\Flight;
use App\Support\Distance;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.logostream.key' => 'test-key']);
});

it('enriches a flight from the aviation api and updates the csv and database', function () {
    Airport::create(['iata_code' => 'AAA', 'icao_code' => 'AAAA', 'name' => 'Alpha', 'city' => 'Alpha', 'country' => 'AA', 'latitude' => 1, 'longitude' => 1]);
    Airport::create(['iata_code' => 'BBB', 'icao_code' => 'BBBB', 'name' => 'Bravo', 'city' => 'Bravo', 'country' => 'BB', 'latitude' => 2, 'longitude' => 2]);

    $flight = Flight::factory()->create([
        'occurred_at' => '2020-01-01T10:00',
        'flight_number' => '999',
        'origin_iata' => 'AAA',
        'destination_iata' => 'BBB',
        'distance' => Distance::fromMiles(1000),
        'duration' => null,
        'departure_timezone' => null,
        'arrival_timezone' => null,
    ]);

    Http::fake([
        '*aviation-api*' => Http::response([
            'data' => [[
                'duration_min' => 120,
                'departure_timezone' => 'Europe/London',
                'arrival_timezone' => 'Europe/Paris',
                'distance_km' => 3218,
            ]],
        ]),
    ]);

    $csv = tempnam(sys_get_temp_dir(), 'flights').'.csv';
    file_put_contents($csv, 'occurred_at,flight_number,airline_icao,origin_iata,destination_iata,distance,cabin_class,reason,meta'.PHP_EOL."2020-01-01T10:00,999,XXX,AAA,BBB,{$flight->distance},economy,,{}\n");

    $this->artisan('flights:enrich', ['--file' => $csv])->assertExitCode(0);

    $flight->refresh();
    expect($flight->duration)->toBe(7200); // 120 min → seconds
    expect($flight->departure_timezone)->toBe('Europe/London');
    expect($flight->arrival_timezone)->toBe('Europe/Paris');
    expect(Distance::miles($flight->distance))->toBe(2000); // 3218 km → miles

    expect(file_get_contents($csv))->toContain('duration')->toContain('Europe/London');

    @unlink($csv);
});

it('falls back to the timezone api when the route is unknown', function () {
    Airport::create(['iata_code' => 'CCC', 'icao_code' => 'CCCC', 'name' => 'Charlie', 'city' => 'Charlie', 'country' => 'CC', 'latitude' => 10, 'longitude' => 10]);
    Airport::create(['iata_code' => 'DDD', 'icao_code' => 'DDDD', 'name' => 'Delta', 'city' => 'Delta', 'country' => 'DD', 'latitude' => 20, 'longitude' => 20]);

    $flight = Flight::factory()->create([
        'occurred_at' => '2021-05-05T08:00',
        'flight_number' => '111',
        'origin_iata' => 'CCC',
        'destination_iata' => 'DDD',
        'distance' => Distance::fromMiles(500),
        'departure_timezone' => null,
    ]);

    Http::fake([
        '*aviation-api*' => Http::response(['data' => []]),
        '*timeapi.io*' => Http::response(['timeZone' => 'Asia/Tokyo']),
    ]);

    $csv = tempnam(sys_get_temp_dir(), 'flights').'.csv';
    file_put_contents($csv, 'occurred_at,flight_number,airline_icao,origin_iata,destination_iata,distance,cabin_class,reason,meta'.PHP_EOL."2021-05-05T08:00,111,XXX,CCC,DDD,{$flight->distance},economy,,{}\n");

    $this->artisan('flights:enrich', ['--file' => $csv])->assertExitCode(0);

    $flight->refresh();
    expect($flight->departure_timezone)->toBe('Asia/Tokyo');
    expect($flight->arrival_timezone)->toBe('Asia/Tokyo');
    expect($flight->duration)->not->toBeNull(); // distance estimate (seconds)
    expect(Distance::miles($flight->distance))->toBeGreaterThan(900)->toBeLessThan(1020); // great-circle from airport coords

    @unlink($csv);
});
