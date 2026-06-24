<?php

use App\Models\Fuel;
use App\Models\FuelStation;

it('associates a fuel entry with a fuel station', function () {
    $station = FuelStation::factory()->create([
        'name' => 'Shell M6',
        'city' => 'Birmingham',
        'country' => 'United Kingdom',
    ]);

    $fuel = Fuel::factory()->create([
        'fuel_station_id' => $station->id,
    ]);

    expect($fuel->fresh()->fuelStation)
        ->not->toBeNull()
        ->name->toBe('Shell M6');
});

it('uses the linked station name as the card title when loaded', function () {
    $station = FuelStation::factory()->create(['name' => 'BP A1']);

    $fuel = Fuel::factory()->create([
        'fuel_station_id' => $station->id,
    ])->load('fuelStation');

    expect($fuel->card()['title'])->toBe('BP A1');
});
