<?php

use App\Models\Fuel;
use Illuminate\Support\Facades\File;

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

afterEach(function () {
    File::delete(public_path('logos/brands/bp.png'));
});

it('exposes the brand logo url on the card when the file exists', function () {
    File::ensureDirectoryExists(public_path('logos/brands'));
    File::put(public_path('logos/brands/bp.png'), 'x');
    $fuel = Fuel::factory()->create(['brand' => 'BP']);

    expect($fuel->card()['meta']['brandLogo'])->toBe('/logos/brands/bp.png');
    expect($fuel->logo_url)->toBe('/logos/brands/bp.png');
});

it('has a null brand logo when the file is absent or brand is null', function () {
    expect(Fuel::factory()->create(['brand' => 'BP'])->card()['meta']['brandLogo'])->toBeNull();
    expect(Fuel::factory()->create(['brand' => null])->logo_url)->toBeNull();
});
