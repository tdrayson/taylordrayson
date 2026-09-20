<?php

use App\Enums\ExportFormat;
use App\Models\Fuel;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;
use App\Support\SerialNumber;

it('prints a fuel fill-up as a forecourt receipt', function () {
    $fuel = Fuel::factory()->create([
        'occurred_at' => '2026-08-28 15:37:23',
        'vehicle_id' => 'hn14wxp',
        'station_name' => 'Beddington Lane Service Station',
        'brand' => 'BP',
        'address' => 'Beddington Lane Beddington',
        'city' => 'Croydon',
        'postcode' => 'CR0 4TJ',
        'litres' => 31.279,
        'price_per_litre' => 1.619,
        'cost' => 50.64,
        'odometer' => 72305,
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($fuel);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toStartWith('=')
        ->and($txt)->toContain('BEDDINGTON LANE SERVICE STATION')
        ->and($txt)->toContain('CROYDON, CR0 4TJ')
        // The postcode must never split across lines the way it did in the boxed version.
        ->and($txt)->not->toContain("CR0\n")
        ->and($txt)->toContain('28-AUG-2026 15:37')
        ->and($txt)->toContain('No. '.SerialNumber::for($fuel->occurred_at))
        ->and($txt)->toContain('BP GARAGE')
        ->and($txt)->toContain('FUEL TYPE: PETROL (E10)')
        ->and($txt)->toContain('31.28 L')
        ->and($txt)->toContain('161.9p per litre')
        ->and($txt)->toContain('TOTAL FUEL')
        ->and($txt)->toContain('£50.64')
        ->and($txt)->toContain('ODOMETER: 72,305 miles')
        ->and($txt)->not->toContain('72305');

    foreach (explode("\n", $txt) as $line) {
        expect(mb_strwidth($line))->toBeLessThanOrEqual(46);
    }
});

it('prints the price row only when the fill carries a unit price', function () {
    $fuel = Fuel::factory()->create([
        'occurred_at' => '2026-09-13 08:00:00',
        'station_name' => 'Beddington Lane Service Station',
        'litres' => 42.5,
        'price_per_litre' => null,
        'cost' => 68.81,
        'odometer' => 45231,
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($fuel);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain('42.50 L')
        ->and($txt)->not->toContain('PRICE')
        ->and($txt)->toContain('TOTAL FUEL')
        ->and($txt)->toContain('£68.81');
});

it('omits the odometer row when a fuel fill-up carries no reading', function () {
    $fuel = Fuel::factory()->create([
        'occurred_at' => '2026-09-13 08:00:00',
        'station_name' => 'Beddington Lane Service Station',
        'litres' => 42.5,
        'price_per_litre' => 1.619,
        'cost' => 68.81,
        'odometer' => null,
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($fuel);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->not->toContain('ODOMETER');
});

it('omits the fuel type row for an unrecognised vehicle', function () {
    $fuel = Fuel::factory()->create([
        'occurred_at' => '2026-09-13 08:00:00',
        'vehicle_id' => 'unknown-plate',
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($fuel);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->not->toContain('FUEL TYPE');
});

it('opens on a bare FUEL heading rather than an empty frame when the vendor is unknown', function () {
    $fuel = Fuel::factory()->create([
        'occurred_at' => '2021-08-26 23:00:00',
        'vehicle_id' => 'hn14wxp',
        'station_name' => null,
        'brand' => null,
        'address' => null,
        'city' => null,
        'postcode' => null,
        'litres' => 30.0,
        'price_per_litre' => 1.2,
        'cost' => 36.0,
        'odometer' => 40000,
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($fuel);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain('FUEL')
        ->and($txt)->not->toContain('==============================================
==============================================')
        ->and($txt)->toContain('No. '.SerialNumber::for($fuel->occurred_at))
        ->and($txt)->toContain('TOTAL FUEL')
        ->and($txt)->not->toContain('UNKNOWN');
});

it('renders the same barcode for the same fill every time', function () {
    $fuel = Fuel::factory()->create(['occurred_at' => '2026-09-13 08:00:00', 'status' => 'published']);

    $data = ExportPresenter::for($fuel);
    $first = Formats::find($data, ExportFormat::Txt)->render($data);
    $second = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($first)->toBe($second);
});
