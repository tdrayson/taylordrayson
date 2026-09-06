<?php

use App\Models\Fuel;
use App\Presenters\CardPresenter;
use Illuminate\Support\Facades\File;

it('uses the flat station_name as the card title', function () {
    $fuel = Fuel::factory()->create([
        'station_name' => 'ASDA Wallington',
        'litres' => 32.13,
        'cost' => 41.13,
        'price_per_litre' => 1.28,
    ]);

    $card = CardPresenter::for($fuel);

    expect($card->title)->toBe('£41.13 at ASDA Wallington');
    expect($card->titleLabel)->toContain('ASDA Wallington');
    expect($card->subtitle)->toContain('32.13L');
});

// The imported rows carry no station, city, brand or coordinates, so the title
// names no place rather than inventing one.
it('names no place when no station is set', function () {
    $fuel = Fuel::factory()->create(['station_name' => null, 'cost' => 41.13]);

    expect(CardPresenter::for($fuel)->title)->toBe('£41.13 at the pump');
});

afterEach(function () {
    File::delete(public_path('logos/brands/testco.png'));
});

it('exposes the brand logo url on the card when the file exists', function () {
    File::ensureDirectoryExists(public_path('logos/brands'));
    File::put(public_path('logos/brands/testco.png'), 'x');
    $fuel = Fuel::factory()->create(['brand' => 'Testco']);

    $meta = CardPresenter::for($fuel)->meta;

    expect($meta->brandLogo)->toBe('/logos/brands/testco.png');
    expect($meta->brand)->toBe('Testco');
    expect($fuel->logo_url)->toBe('/logos/brands/testco.png');
});

it('has a null brand logo when the file is absent or brand is null', function () {
    expect(CardPresenter::for(Fuel::factory()->create(['brand' => 'Testco']))->meta->brandLogo)->toBeNull();
    expect(Fuel::factory()->create(['brand' => null])->logo_url)->toBeNull();
});

it('slugs the station name for the entry URL', function () {
    $fuel = Fuel::factory()->make(['station_name' => 'Shell Cobham Services']);

    expect($fuel->slug())->toBe('shell-cobham-services');
});

it('falls back to the "fuel" slug when no station is set', function () {
    expect(Fuel::factory()->make(['station_name' => null])->slug())->toBe('fuel');
    expect(Fuel::factory()->make(['station_name' => ''])->slug())->toBe('fuel');
});
