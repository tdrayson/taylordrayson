<?php

use App\Enums\ExportFormat;
use App\Models\Fuel;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('prints a fuel fill-up as a till receipt', function () {
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
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($fuel);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toStartWith('+')
        ->and($txt)->toContain('Beddington Lane Service Station (BP)')
        ->and($txt)->toContain('42.50 L @ 161.9p per litre')
        ->and($txt)->toContain('TOTAL')
        ->and($txt)->toContain('£68.81')
        ->and($txt)->toContain('ODOMETER')
        ->and($txt)->toContain('45,231 miles')
        // 45231 is the raw odometer reading; the sheet must print "45,231".
        ->and($txt)->not->toContain('45231');

    foreach (explode("\n", $txt) as $line) {
        expect(mb_strwidth($line))->toBeLessThanOrEqual(46);
    }
});

it('prints the item line without a unit price when the fill carries none', function () {
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
        ->and($txt)->not->toContain('@')
        ->and($txt)->toContain('TOTAL')
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
