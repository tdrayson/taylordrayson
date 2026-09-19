<?php

use App\Enums\ExportFormat;
use App\Models\Place;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('prints a place check-in as a boxed passport stamp', function () {
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
        ->and($txt)->toContain('CR0 1AB')
        // "coffee-shop" is the raw category slug; the sheet must print "Coffee Shop".
        ->and($txt)->not->toContain('coffee-shop');
});
