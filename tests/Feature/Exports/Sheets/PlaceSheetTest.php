<?php

use App\Enums\ExportFormat;
use App\Models\Place;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('prints a place check-in as the back of a postcard', function () {
    $place = Place::factory()->create([
        'occurred_at' => '2026-09-13 10:00:00',
        'venue_name' => 'Costa Coffee',
        'type' => 'coffee-shop',
        'address' => '12 High Street',
        'city' => 'Croydon',
        'postcode' => 'CR0 1AB',
        'country' => 'United Kingdom',
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($place);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain('Costa Coffee')
        ->and($txt)->toContain('Coffee Shop')
        ->and($txt)->toContain('12 High Street')
        ->and($txt)->toContain('1AB')
        // "coffee-shop" is the raw category slug; the sheet must print "Coffee Shop".
        ->and($txt)->not->toContain('coffee-shop');
});

it('keeps a long address inside the card rather than running it off the edge', function () {
    $place = Place::factory()->create([
        'occurred_at' => '2026-09-13 10:00:00',
        'venue_name' => 'Costa Coffee',
        'address' => '12 High Street',
        'city' => 'Croydon',
        'postcode' => 'CR0 1AB',
        'country' => 'United Kingdom',
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($place);
    $lines = explode("\n", trim(Formats::find($data, ExportFormat::Txt)->render($data)));

    // The address is wider than the card, so it wraps across lines. Read the
    // card back with its borders stripped: every part must survive the wrap.
    $written = preg_replace('/\s+/', ' ', implode(' ', array_map(
        fn (string $line): string => trim($line, '|+- '),
        $lines,
    )));

    expect($written)->toContain('12 High Street')
        ->and($written)->toContain('Croydon')
        ->and($written)->toContain('CR0 1AB')
        ->and($written)->toContain('United Kingdom');

    // Nothing may spill past the border on the way.
    foreach ($lines as $line) {
        expect(mb_strwidth($line))->toBe(64);
    }
});
