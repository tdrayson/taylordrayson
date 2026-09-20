<?php

use App\Enums\ExportFormat;
use App\Models\Film;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;
use App\Presenters\Exports\Sheets\FilmSheet;
use App\Support\SerialNumber;

it('prints a film as a perforated cinema ticket stub, one field per row', function () {
    $film = Film::factory()->create([
        'occurred_at' => '2026-09-19 20:00:00',
        'title' => 'Fall 2: Deadpoint',
        'rating' => 8,
        'meta' => ['year' => 2026, 'runtime' => 98],
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($film);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    $rows = ticketRows($txt);

    expect($txt)->toContain('CINEMA TICKET')
        ->and($txt)->toContain('ADMIT ONE')
        ->and($txt)->toContain('Ref. '.SerialNumber::for($film->occurred_at, $film->id))
        ->and($txt)->toContain('TAYLORDRAYSON')
        ->and(array_map(fn (array $r): array => [$r[0], $r[1]], $rows))
        ->toContain(
            ['FILM', 'Fall 2: Deadpoint'],
            ['DATE', '19 Sep 2026'],
            ['RELEASE', '2026'],
            ['RATED', '8 out of 10'],
            ['RUNTIME', '1h 38m'],
        );
});

it('starts and ends every ticket row in the same column, whichever tear edge it sits on', function () {
    $film = Film::factory()->create([
        'occurred_at' => '2026-09-19 20:00:00',
        'title' => 'Fall 2: Deadpoint',
        'rating' => 8,
        'meta' => ['year' => 2026, 'runtime' => 98],
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($film);
    $rows = ticketRows(Formats::find($data, ExportFormat::Txt)->render($data));

    // The '(' and ')' shapes inset by different amounts, so an uncorrected
    // row zigzags one column per line.
    expect(array_unique(array_column($rows, 2)))->toHaveCount(1)
        ->and(array_unique(array_column($rows, 3)))->toHaveCount(1);
});

it('keeps every line the same width as its declared ticket width', function () {
    $film = Film::factory()->create([
        'occurred_at' => '2026-09-19 20:00:00',
        'title' => 'Fall 2: Deadpoint',
        'rating' => 8,
        'meta' => ['year' => 2026, 'runtime' => 98],
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($film);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    $widths = array_map('mb_strwidth', explode("\n", trim($txt)));

    expect(array_unique($widths))->toHaveCount(1)
        ->and($widths[0])->toBe(FilmSheet::WIDTH);
});

it('widens the ticket rather than truncating a title too long for the default width', function () {
    $title = 'A Film With A Genuinely Very Long Title That Keeps On Going Past The Usual Stub Width';
    $film = Film::factory()->create([
        'occurred_at' => '2026-09-19 20:00:00',
        'title' => $title,
        'rating' => null,
        'meta' => ['year' => null, 'runtime' => null],
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($film);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    $widths = array_map('mb_strwidth', explode("\n", trim($txt)));

    expect($txt)->toContain($title)
        ->and(array_unique($widths))->toHaveCount(1)
        ->and($widths[0])->toBeGreaterThan(FilmSheet::WIDTH);
});

it('omits the rating from the paired row, but keeps the date, when a film carries no rating', function () {
    $film = Film::factory()->create([
        'occurred_at' => '2026-09-13 20:00:00',
        'rating' => null,
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($film);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->not->toContain('out of 10')
        ->and(array_column(ticketRows($txt), 0))->toContain('DATE');
});

it('renders the same barcode for the same film every time', function () {
    $film = Film::factory()->create(['occurred_at' => '2026-09-13 20:00:00', 'status' => 'published']);

    $data = ExportPresenter::for($film);
    $first = Formats::find($data, ExportFormat::Txt)->render($data);
    $second = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($first)->toBe($second);
});
