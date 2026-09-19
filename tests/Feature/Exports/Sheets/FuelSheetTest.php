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

    expect($txt)->toContain('Beddington Lane Service Station (BP)')
        ->and($txt)->toContain('Cost')
        ->and($txt)->toContain('£68.81')
        ->and($txt)->toContain('45,231 miles')
        // 45231 is the raw odometer reading; the sheet must print "45,231".
        ->and($txt)->not->toContain('45231')
        // A single-item receipt does not need its one cost repeated as a total.
        ->and($txt)->not->toContain('TOTAL');
});
