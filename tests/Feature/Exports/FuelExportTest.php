<?php

use App\Models\Fuel;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;
use App\Support\SerialNumber;

it('publishes a fuel fill-up as labelled fields in order', function () {
    $fuel = Fuel::factory()->create([
        'occurred_at' => '2026-09-13 08:00:00',
        'vehicle_id' => 'hn14wxp',
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
        ->toBe(['station', 'brand', 'fuel_type', 'locality', 'location', 'receipt_time', 'receipt_ref', 'litres', 'price_per_litre', 'cost', 'odometer'])
        ->and($export->field('station')->display)->toBe('Beddington Lane Service Station')
        ->and($export->field('brand')->display)->toBe('BP')
        ->and($export->field('fuel_type')->display)->toBe('Petrol (E10)')
        ->and($export->field('locality')->display)->toBe('Croydon, CR0 4TQ')
        ->and($export->field('location')->display)->toBe('1 Beddington Lane, Croydon, CR0 4TQ')
        ->and($export->field('receipt_time')->display)->toBe('13-SEP-2026 08:00')
        ->and($export->field('receipt_ref')->display)->toBe(SerialNumber::for($fuel->occurred_at))
        ->and($export->field('litres')->display)->toBe('42.50 L')
        ->and($export->field('price_per_litre')->display)->toBe('161.9p per litre')
        ->and($export->field('cost')->display)->toBe('£68.81')
        ->and($export->field('odometer')->display)->toBe('45,231 miles');
});

it('grades petrol E5 before the UK E10 switch and E10 after it', function () {
    $before = Fuel::factory()->create(['occurred_at' => '2021-08-31 08:00:00', 'vehicle_id' => 'hn14wxp', 'status' => 'published']);
    $after = Fuel::factory()->create(['occurred_at' => '2021-09-01 08:00:00', 'vehicle_id' => 'hn14wxp', 'status' => 'published']);

    expect(ExportPresenter::for($before)->field('fuel_type')->display)->toBe('Petrol (E5)')
        ->and(ExportPresenter::for($after)->field('fuel_type')->display)->toBe('Petrol (E10)');
});

it('omits the fuel type row for an unrecognised vehicle', function () {
    $fuel = Fuel::factory()->create(['occurred_at' => '2026-09-13 08:00:00', 'vehicle_id' => 'unknown-plate', 'status' => 'published']);

    expect(ExportPresenter::for($fuel)->field('fuel_type'))->toBeNull();
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
