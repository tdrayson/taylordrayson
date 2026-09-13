<?php

use App\Models\Fuel;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

function fakeCountyLookup(array $properties): void
{
    Saloon::fake(['petrolprices.com/app/geojson*' => MockResponse::make(['data' => ['features' => [[
        'type' => 'Feature',
        'geometry' => ['coordinates' => [-0.1318, 51.3731]],
        'properties' => $properties,
    ]]]])]);
}

it('fills the county on rows matched by postcode', function () {
    fakeCountyLookup([
        'fuel_brand_name' => 'BP',
        'name' => 'BP CROYDON (BEDDINGTON LANE SERVICE STATION)',
        'town' => 'CROYDON', 'county' => 'GREATER LONDON', 'postcode' => 'CR0 4TJ',
        'distance_in_miles_from_given_coords' => 0.01,
    ]);
    $fuel = Fuel::factory()->create([
        'station_name' => 'Beddington Lane Service Station',
        'postcode' => 'CR0 4TJ', 'latitude' => 51.3731, 'longitude' => -0.1318, 'county' => null,
    ]);

    $this->artisan('fuel:counties', ['--apply' => true])->assertExitCode(0);

    expect($fuel->fresh()->county)->toBe('Greater London');
});

it('matches on station name when the stored postcode is stale', function () {
    fakeCountyLookup([
        'fuel_brand_name' => 'ESSO',
        'name' => 'ESSO HORLEY (MFG HORLEY)',
        'town' => 'HORLEY', 'county' => 'WEST SUSSEX', 'postcode' => 'RH6 7HH',
        'distance_in_miles_from_given_coords' => 0.2,
    ]);
    $fuel = Fuel::factory()->create([
        'station_name' => 'MFG Horley',
        'postcode' => 'RH6 0AD', 'latitude' => 51.17, 'longitude' => -0.16, 'county' => null,
    ]);

    $this->artisan('fuel:counties', ['--apply' => true])->assertExitCode(0);

    expect($fuel->fresh()->county)->toBe('West Sussex');
});

it('leaves the county alone when nothing nearby matches the row', function () {
    fakeCountyLookup([
        'fuel_brand_name' => 'SHELL',
        'name' => 'SHELL COBHAM (M25 COBHAM MOTORWAY SERVICE AREA)',
        'town' => 'COBHAM', 'county' => 'SURREY', 'postcode' => 'KT11 3DB',
        'distance_in_miles_from_given_coords' => 0.4,
    ]);
    $fuel = Fuel::factory()->create([
        'station_name' => 'Shell Cobham',
        'postcode' => 'KT11 3JS', 'latitude' => 51.32, 'longitude' => -0.40, 'county' => null,
    ]);

    $this->artisan('fuel:counties', ['--apply' => true])->assertExitCode(0);

    expect($fuel->fresh()->county)->toBeNull();
});

it('writes nothing without --apply', function () {
    fakeCountyLookup([
        'fuel_brand_name' => 'BP',
        'name' => 'BP CROYDON (BEDDINGTON LANE SERVICE STATION)',
        'town' => 'CROYDON', 'county' => 'GREATER LONDON', 'postcode' => 'CR0 4TJ',
        'distance_in_miles_from_given_coords' => 0.01,
    ]);
    $fuel = Fuel::factory()->create([
        'station_name' => 'Beddington Lane Service Station',
        'postcode' => 'CR0 4TJ', 'latitude' => 51.3731, 'longitude' => -0.1318, 'county' => null,
    ]);

    $this->artisan('fuel:counties')->assertExitCode(0);

    expect($fuel->fresh()->county)->toBeNull();
});

it('skips rows that already have a county', function () {
    Fuel::factory()->create(['latitude' => 51.3731, 'longitude' => -0.1318, 'county' => 'Surrey']);

    $this->artisan('fuel:counties', ['--apply' => true])->assertExitCode(0);

    Saloon::assertNothingSent();
});
