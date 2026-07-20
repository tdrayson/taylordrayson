<?php

use App\Models\Fuel;
use App\Presenters\CardPresenter;

it('uses the flat station_name as the card title', function () {
    $fuel = Fuel::factory()->create([
        'station_name' => 'ASDA Wallington',
        'litres' => 32.13,
        'cost' => 41.13,
        'price_per_litre' => 1.28,
    ]);

    $card = CardPresenter::for($fuel);

    expect($card->title)->toBe('ASDA Wallington');
    expect($card->titleLabel)->toContain('ASDA Wallington');
    expect($card->subtitle)->toContain('£41.13');
});

it('falls back to Fuel when no station is set', function () {
    $fuel = Fuel::factory()->create(['station_name' => null]);

    expect(CardPresenter::for($fuel)->title)->toBe('Fuel');
});
