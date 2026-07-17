<?php

use App\Models\Fuel;

it('uses the flat station_name as the card title', function () {
    $fuel = Fuel::factory()->create([
        'station_name' => 'ASDA Wallington',
        'litres' => 32.13,
        'cost' => 41.13,
        'price_per_litre' => 1.28,
    ]);

    $card = $fuel->card();

    expect($card['title'])->toBe('ASDA Wallington');
    expect($card['titleLabel'])->toContain('ASDA Wallington');
    expect($card['subtitle'])->toContain('£41.13');
});

it('falls back to Fuel when no station is set', function () {
    $fuel = Fuel::factory()->create(['station_name' => null]);

    expect($fuel->card()['title'])->toBe('Fuel');
});

it('slugs the station name for the entry URL', function () {
    $fuel = Fuel::factory()->make(['station_name' => 'Shell Cobham Services']);

    expect($fuel->slug())->toBe('shell-cobham-services');
});

it('falls back to the "fuel" slug when no station is set', function () {
    expect(Fuel::factory()->make(['station_name' => null])->slug())->toBe('fuel');
    expect(Fuel::factory()->make(['station_name' => ''])->slug())->toBe('fuel');
});
