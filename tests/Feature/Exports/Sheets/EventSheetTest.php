<?php

use App\Enums\ExportFormat;
use App\Models\Event;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;
use App\Presenters\Exports\Sheets\EventSheet;

it('prints an event as a perforated ticket stub, with doors from the occurred instant and ends from the field', function () {
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

    $rows = array_map(fn (array $r): array => [$r[0], $r[1]], ticketRows($txt));

    expect($txt)->toContain('EVENT TICKET')
        ->and($txt)->toContain('ADMIT ONE')
        ->and($txt)->toContain('No. '.str_pad((string) $event->id, 10, '0', STR_PAD_LEFT))
        ->and($txt)->toContain('TAYLORDRAYSON')
        ->and($rows)->toContain(
            ['START', '13 September 2026 at 19:30'],
            ['END', '13 September 2026 at 22:15'],
        )
        ->and(array_column($rows, 0))->toContain('EVENT', 'VENUE');
});

it('keeps every line the same width as its declared ticket width', function () {
    $event = Event::factory()->create(['occurred_at' => '2026-09-13 19:30:00', 'status' => 'published']);

    $data = ExportPresenter::for($event);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    $widths = array_map('mb_strwidth', explode("\n", trim($txt)));

    expect(array_unique($widths))->toHaveCount(1)
        ->and($widths[0])->toBe(EventSheet::WIDTH);
});

it('widens the ticket rather than truncating an event or venue name too long for the default width', function () {
    $event = Event::factory()->create([
        'occurred_at' => '2026-09-13 19:30:00',
        'name' => "Jeff Wayne's The War of The Worlds: The Immersive Experience",
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($event);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    $widths = array_map('mb_strwidth', explode("\n", trim($txt)));

    expect($txt)->toContain("Jeff Wayne's The War of The Worlds: The Immersive Experience")
        ->and(array_unique($widths))->toHaveCount(1)
        ->and($widths[0])->toBeGreaterThan(EventSheet::WIDTH);
});

it('omits the venue and ends rows when an event carries neither', function () {
    $event = Event::factory()->create([
        'occurred_at' => '2026-09-13 19:30:00',
        'venue_name' => null,
        'ends_at' => null,
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($event);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    $labels = array_column(ticketRows($txt), 0);

    expect($labels)->toContain('START')
        ->and($labels)->not->toContain('VENUE')
        ->and($labels)->not->toContain('END');
});

it('renders the same barcode for the same event every time', function () {
    $event = Event::factory()->create(['occurred_at' => '2026-09-13 19:30:00', 'status' => 'published']);

    $data = ExportPresenter::for($event);
    $first = Formats::find($data, ExportFormat::Txt)->render($data);
    $second = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($first)->toBe($second);
});
