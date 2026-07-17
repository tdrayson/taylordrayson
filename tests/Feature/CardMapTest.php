<?php

use App\Models\Activity;
use App\Models\Fuel;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Media library writes to the `public` disk by default; fake it so attached
    // images never leak onto the real disk (RefreshDatabase only rolls back rows).
    Storage::fake('public');
});

it('exposes a stored map url on the fuel card when media is attached', function () {
    $fuel = Fuel::factory()->create(['station_name' => 'Test Garage']);
    $fuel->addMediaFromString('PNG')->usingFileName('m.png')->toMediaCollection('map');

    $meta = $fuel->card()['meta'];

    expect($meta['map'])->not->toBeNull();
});

it('has a null map on the fuel card when no media is attached', function () {
    $fuel = Fuel::factory()->create();

    expect($fuel->card()['meta']['map'])->toBeNull();
    expect($fuel->card()['meta']['mapDark'])->toBeNull();
});

it('exposes stored map urls on the activity card while keeping its polyline', function () {
    $activity = Activity::factory()->create(['meta' => ['polyline' => '_p~iF~ps|U']]);
    $activity->addMediaFromString('PNG')->usingFileName('m.png')->toMediaCollection('map_dark');

    $meta = $activity->card()['meta'];

    expect($meta['mapDark'])->not->toBeNull();
    expect($meta['polyline'])->toBe('_p~iF~ps|U');
});
