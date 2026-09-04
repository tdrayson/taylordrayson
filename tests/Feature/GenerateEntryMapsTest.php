<?php

use App\Models\Fuel;
use Illuminate\Support\Facades\Storage;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function () {
    Storage::fake('public');
    config(['services.mapbox.token' => 'test-token']);
});

it('skips rows that already have a map unless forced', function () {
    Saloon::fake(['api.mapbox.com*' => MockResponse::make(mapPng(), 200)]);

    $fuel = Fuel::factory()->create(['latitude' => 51.3, 'longitude' => -0.1]);
    $fuel->addMediaFromString(mapPng())->usingFileName('m.png')->toMediaCollection('map');

    $this->artisan('maps:generate', ['type' => 'fuel'])->assertSuccessful();

    expect($fuel->fresh()->getMedia('map'))->toHaveCount(1);
});

it('processes at most the --limit number of entries', function () {
    Saloon::fake(['api.mapbox.com*' => MockResponse::make(mapPng(), 200)]);

    Fuel::factory()->count(3)->create(['latitude' => 51.3, 'longitude' => -0.1]);

    $this->artisan('maps:generate', ['type' => 'fuel', '--limit' => 1])->assertSuccessful();

    $mapped = Fuel::query()
        ->whereHas('media', fn ($query) => $query->where('collection_name', 'map'))
        ->count();

    expect($mapped)->toBe(1);
});
