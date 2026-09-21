<?php

use App\Enums\ExportFormat;
use App\Models\Flight;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;
use App\Presenters\Exports\Sheets\FlightSheet;

it('prints a flight as a wide, three-column boarding pass', function () {
    $data = ExportPresenter::for(krkToLgw());
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain('BOARDING PASS')
        ->and($txt)->toContain('ECONOMY')
        ->and($txt)->toContain('easyJet UK')
        ->and($txt)->toContain('FLIGHT: U2 8824')
        ->and($txt)->toContain('FROM: KRK / Kraków')
        ->and($txt)->toContain('TO: LGW / London')
        ->and($txt)->toContain('DATE: 08 Jun 2026')
        ->and($txt)->toContain('DEPARTS: 22:15')
        ->and($txt)->toContain('ARRIVES: 23:42')
        ->and($txt)->toContain('DURATION: 2h 27m')
        ->and($txt)->toContain('DISTANCE: 876 miles')
        ->and($txt)->toContain('REASON: Business')
        ->and($txt)->toContain('PASSENGER: DRAYSON / TAYLOR')
        ->and($txt)->not->toContain('1409785')
        ->and($txt)->not->toContain('8820')
        ->and($txt)->not->toContain('CABIN')
        ->and($txt)->not->toContain('GATE')
        ->and($txt)->not->toContain('SEAT');
});

it('keeps every line the same width as its declared boarding pass width', function () {
    $data = ExportPresenter::for(krkToLgw());
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    $widths = array_map('mb_strwidth', explode("\n", trim($txt)));

    expect(array_unique($widths))->toHaveCount(1)
        ->and($widths[0])->toBe(FlightSheet::WIDTH);
});

it('renders a coherent grid, not a hole, when a flight carries no reason or distance', function () {
    $flight = Flight::factory()->create([
        'occurred_at' => '2026-06-08 22:15:00',
        'flight_number' => '123',
        'airline_icao' => 'ZZZ',
        'origin_iata' => 'ZZZ',
        'destination_iata' => 'YYY',
        'distance' => null,
        'duration' => null,
        'departure_timezone' => null,
        'arrival_timezone' => null,
        'cabin_class' => null,
        'reason' => null,
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($flight);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    $widths = array_map('mb_strwidth', explode("\n", trim($txt)));

    expect($txt)->toContain('FLIGHT: ZZZ 123')
        ->and($txt)->not->toContain('DISTANCE:')
        ->and($txt)->not->toContain('REASON:')
        ->and(array_unique($widths))->toHaveCount(1)
        ->and($widths[0])->toBe(FlightSheet::WIDTH);
});

it('renders the same barcode for the same flight every time', function () {
    $data = ExportPresenter::for(krkToLgw());
    $first = Formats::find($data, ExportFormat::Txt)->render($data);
    $second = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($first)->toBe($second);
});
