<?php

use App\Models\Fuel;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('publishes a fuel fill-up as labelled fields in order', function () {
    $fuel = Fuel::factory()->create([
        'occurred_at' => '2026-09-13 08:00:00',
        'station_name' => 'Beddington Lane Service Station',
        'brand' => 'BP',
        'address' => '1 Beddington Lane',
        'city' => 'Croydon',
        'postcode' => 'CR0 4TQ',
        'litres' => 42.5,
        'price_per_litre' => 1.619,
        'cost' => 68.81,
        'odometer' => 45231,
        'latitude' => 51.376,
        'longitude' => -0.098,
        'status' => 'published',
    ]);

    $export = ExportPresenter::for($fuel);

    expect(array_map(fn ($f) => $f->key, $export->fields))
        ->toBe(['station', 'location', 'litres', 'price_per_litre', 'cost', 'odometer'])
        ->and($export->field('station')->display)->toBe('Beddington Lane Service Station (BP)')
        ->and($export->field('station')->raw)->toBe('Beddington Lane Service Station')
        ->and($export->field('location')->display)->toBe('1 Beddington Lane, Croydon, CR0 4TQ')
        ->and($export->field('litres')->display)->toBe('42.50 L')
        ->and($export->field('price_per_litre')->display)->toBe('161.9p per litre')
        ->and($export->field('cost')->display)->toBe('£68.81')
        ->and($export->field('odometer')->display)->toBe('45,231 miles');
});

it('offers geojson for a fuel stop only when it was geocoded', function () {
    $located = Fuel::factory()->create(['occurred_at' => '2026-09-13 08:00:00', 'latitude' => 51.376, 'longitude' => -0.098, 'status' => 'published']);
    $unlocated = Fuel::factory()->create(['occurred_at' => '2026-09-13 08:00:00', 'latitude' => null, 'longitude' => null, 'status' => 'published']);

    $locatedFormats = array_keys(Formats::for(ExportPresenter::for($located)));
    $unlocatedFormats = array_keys(Formats::for(ExportPresenter::for($unlocated)));

    expect($locatedFormats)->toContain('geojson')
        ->and($unlocatedFormats)->not->toContain('geojson')
        ->and($unlocatedFormats)->not->toContain('ics');
});

it('never leaks an id, a timestamp or a password', function () {
    $fuel = Fuel::factory()->create(['occurred_at' => '2026-09-13 08:00:00', 'status' => 'published']);

    $json = json_encode(ExportPresenter::for($fuel)->toArray());

    expect($json)->not->toContain('password')
        ->not->toContain('created_at')
        ->not->toContain('updated_at');
});
