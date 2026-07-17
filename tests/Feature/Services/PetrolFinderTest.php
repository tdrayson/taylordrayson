<?php

use App\Services\PetrolFinder;
use App\Services\PetrolFinder\FuelStationResult;
use Illuminate\Support\Facades\Http;

it('searches by coordinates and maps stations to results', function () {
    Http::fake(['*/api/search*' => Http::response(['stations' => [[
        'name' => 'ASDA WALLINGTON SUPERSTORE',
        'brand' => 'ASDA',
        'address' => 'MARLOW WAY, CROYDON',
        'postcode' => 'CR0 4XS',
        'city' => 'CROYDON',
        'latitude' => 51.3767648,
        'longitude' => -0.1313429,
        'distance' => 0.4,
    ]]])]);

    $results = app(PetrolFinder::class)->search(latitude: 51.3731, longitude: -0.1318);

    expect($results)->toHaveCount(1);
    expect($results[0])->toBeInstanceOf(FuelStationResult::class);
    expect($results[0]->stationName)->toBe('ASDA WALLINGTON SUPERSTORE');
    expect($results[0]->brand)->toBe('ASDA');
    expect($results[0]->postcode)->toBe('CR0 4XS');
    expect($results[0]->distance)->toBe(0.4);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'lat=51.3731')
        && str_contains($request->url(), 'lng=-0.1318'));
});

it('sends q for address searches', function () {
    Http::fake(['*/api/search*' => Http::response(['stations' => []])]);

    app(PetrolFinder::class)->searchByAddress('SW1A 1AA');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'q=SW1A'));
});

it('returns the closest station from nearest()', function () {
    Http::fake(['*/api/search*' => Http::response(['stations' => [
        ['name' => 'FAR', 'distance' => 2.5, 'latitude' => 1, 'longitude' => 1],
        ['name' => 'NEAR', 'distance' => 0.3, 'latitude' => 2, 'longitude' => 2],
    ]])]);

    $result = app(PetrolFinder::class)->nearest(51.3731, -0.1318);

    expect($result->stationName)->toBe('NEAR');
});

it('returns an empty array when the api fails', function () {
    Http::fake(['*/api/search*' => Http::response('boom', 500)]);

    expect(app(PetrolFinder::class)->search(latitude: 1, longitude: 1))->toBe([]);
});
