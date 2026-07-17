<?php

use App\Models\Fuel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    config(['services.mapbox.token' => 'test-token']);
});

it('generates pin maps for fuel rows with coordinates', function () {
    Http::fake(['*api.mapbox.com*' => Http::response('PNG', 200)]);

    $fuel = Fuel::factory()->create(['latitude' => 51.3, 'longitude' => -0.1]);
    Fuel::factory()->create(['latitude' => null, 'longitude' => null]);

    $this->artisan('maps:generate', ['type' => 'fuel'])->assertSuccessful();

    expect($fuel->fresh()->getFirstMediaUrl('map'))->not->toBe('');
});

it('skips rows that already have a map unless forced', function () {
    Http::fake(['*api.mapbox.com*' => Http::response('PNG', 200)]);

    $fuel = Fuel::factory()->create(['latitude' => 51.3, 'longitude' => -0.1]);
    $fuel->addMediaFromString('PNG')->usingFileName('m.png')->toMediaCollection('map');

    $this->artisan('maps:generate', ['type' => 'fuel'])->assertSuccessful();

    expect($fuel->fresh()->getMedia('map'))->toHaveCount(1);
});
