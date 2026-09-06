<?php

use App\Services\PetrolPrices\Client;
use App\Services\PetrolPrices\FuelStationResult;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

/**
 * Shape and values captured from a live petrolprices.com response.
 *
 * @param  array<int, array{properties: array<string, mixed>, coordinates?: array<int, float>}>  $stations
 */
function fakePetrolPricesStations(array $stations): void
{
    Saloon::fake(['petrolprices.com/app/geojson*' => MockResponse::make([
        'error' => false,
        'limitExceed' => false,
        'data' => [
            'type' => 'FeatureCollection',
            'features' => array_map(fn (array $s): array => [
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => $s['coordinates'] ?? [-0.077033, 51.304048]],
                'properties' => $s['properties'],
            ], $stations),
        ],
    ])]);
}

it('maps a station, standardises casing and converts the distance to km', function () {
    fakePetrolPricesStations([[
        'coordinates' => [-0.077033, 51.304048],
        'properties' => [
            'fuel_brand_name' => 'SHELL',
            'name' => 'SHELL WHYTELEAFE (SHELL WHYTELEAFE)',
            'address1' => 'GODSTONE ROAD',
            'address2' => '',
            'town' => 'WHYTELEAFE',
            'county' => 'SURREY',
            'postcode' => 'CR3 0BB',
            'distance_in_miles_from_given_coords' => 1.14,
        ],
    ]]);

    $results = app(Client::class)->search(latitude: 51.3024, longitude: -0.0747);

    expect($results)->toHaveCount(1);
    expect($results[0])->toBeInstanceOf(FuelStationResult::class);
    expect($results[0]->stationName)->toBe('Shell Whyteleafe');
    expect($results[0]->brand)->toBe('Shell');
    expect($results[0]->address)->toBe('Godstone Road');
    expect($results[0]->postcode)->toBe('CR3 0BB');
    expect($results[0]->city)->toBe('Whyteleafe');
    // GeoJSON orders coordinates lng-first.
    expect($results[0]->latitude)->toBe(51.304048);
    expect($results[0]->longitude)->toBe(-0.077033);
    // 1.14 miles is 1.835 km. Passing miles straight through would search 60% short.
    expect($results[0]->distanceKm)->toBe(1.835);
});

it('takes the forecourt name from the trailing parenthetical', function () {
    fakePetrolPricesStations([[
        'properties' => [
            'fuel_brand_name' => 'BP',
            'name' => 'BP WHYTELEAFE (GODSTONE ROAD SF CONNECT)',
            'distance_in_miles_from_given_coords' => 0.82,
        ],
    ]]);

    $results = app(Client::class)->search(latitude: 1, longitude: 1);

    expect($results[0]->stationName)->toBe('Godstone Road SF Connect');
    expect($results[0]->brand)->toBe('BP');
});

it('canonicalises the brand name the feed reports', function () {
    fakePetrolPricesStations([
        ['properties' => ['fuel_brand_name' => 'TESCO', 'name' => 'A (A)']],
        ['properties' => ['fuel_brand_name' => 'SAINSBURYS', 'name' => 'B (B)']],
        ['properties' => ['fuel_brand_name' => 'HARVESTENERGY', 'name' => 'C (C)']],
    ]);

    $brands = array_map(fn (FuelStationResult $s): ?string => $s->brand, app(Client::class)->search(latitude: 1, longitude: 1));

    expect($brands)->toBe(['Tesco', "Sainsbury's", 'Harvest Energy']);
});

it('falls back to a title-cased brand when it is not a known brand', function () {
    fakePetrolPricesStations([[
        'properties' => ['fuel_brand_name' => 'INDIE FUELS', 'name' => 'SOME INDIE GARAGE (SOME INDIE GARAGE)'],
    ]]);

    $results = app(Client::class)->search(latitude: 1, longitude: 1);

    expect($results[0]->brand)->toBe('Indie Fuels');
    expect($results[0]->stationName)->toBe('Some Indie Garage');
});

it('asks for distance ordering and a whole-mile radius rounded up', function () {
    fakePetrolPricesStations([]);

    app(Client::class)->search(latitude: 51.3024, longitude: -0.0747, radiusKm: 5);

    // 5 km is 3.1 miles, which must round up to 4 so the search is never narrower than asked.
    // getUrl() is the path only; Saloon keeps the query string separate.
    Saloon::assertSent(function ($request, $response) {
        return str_contains($response->getPendingRequest()->getUrl(), '/app/geojson/2/0/0/0/distance/4')
            && $request->query()->get('lat') === 51.3024;
    });
});

it('returns an empty array when the api fails', function () {
    Saloon::fake(['petrolprices.com/app/geojson*' => MockResponse::make('Not Found', 404)]);

    expect(app(Client::class)->search(latitude: 1, longitude: 1))->toBe([]);
});

it('returns an empty array where there are no stations nearby', function () {
    fakePetrolPricesStations([]);

    expect(app(Client::class)->search(latitude: 56.5, longitude: 3.0))->toBe([]);
});
