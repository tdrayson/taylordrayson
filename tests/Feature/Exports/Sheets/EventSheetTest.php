<?php

use App\Enums\ExportFormat;
use App\Models\Event;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('prints an event as its ticket, with doors from the occurred instant and ends from the field', function () {
    $event = Event::factory()->create([
        'occurred_at' => '2026-09-13 19:30:00',
        'ends_at' => '2026-09-13 22:15:00',
        'name' => "Jeff Wayne's The War of The Worlds: The Immersive Experience",
        'venue_name' => "Jeff Wayne's The War of The Worlds Immersive Experience",
        'address' => '3 Creechurch Lane',
        'city' => 'London',
        'country' => 'United Kingdom',
        'timezone' => 'Europe/London',
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($event);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain("Jeff Wayne's The War of The Worlds")
        ->and($txt)->toContain('3 Creechurch Lane, London, United Kingdom')
        ->and($txt)->toContain('DOORS')
        ->and($txt)->toContain('13 September 2026 at 19:30')
        ->and($txt)->toContain('ENDS');
});

it('omits the ends row when an event carries no end time', function () {
    $event = Event::factory()->create([
        'occurred_at' => '2026-09-13 19:30:00',
        'ends_at' => null,
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($event);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain('DOORS')
        ->and($txt)->not->toContain('ENDS');
});
