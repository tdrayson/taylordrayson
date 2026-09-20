<?php

use App\Enums\ExportFormat;
use App\Models\Place;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('prints a place check-in as a circular passport stamp', function () {
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
        ->and($txt)->toContain('COFFEE SHOP')
        ->and($txt)->toContain('12 High Street')
        ->and($txt)->toContain('1AB')
        // "coffee-shop" is the raw category slug; the sheet must print "Coffee Shop".
        ->and($txt)->not->toContain('coffee-shop');
});

it('keeps a long address inside the stamp rather than dropping or overwriting it', function () {
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

    // The address is wider than the whole stamp, so it has to wrap; every
    // piece of it must still appear, and none may sit on the ring.
    $carrying = array_values(array_filter($lines, fn (string $l): bool => str_contains($l, 'High Street') || str_contains($l, 'United Kingdom')));

    expect($carrying)->not->toBeEmpty()
        ->and(implode(' ', $carrying))->toContain('12 High Street')
        ->and(implode(' ', $carrying))->toContain('United Kingdom');

    foreach ($carrying as $line) {
        expect(trim($line))->toStartWith('*')->and(trim($line))->toEndWith('*');
    }
});
