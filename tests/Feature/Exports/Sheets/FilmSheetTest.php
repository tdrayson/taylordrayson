<?php

use App\Enums\ExportFormat;
use App\Models\Film;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;
use App\Presenters\Exports\Sheets\FilmSheet;

it('prints a film as a perforated cinema ticket stub, with the date and rating paired', function () {
    $film = Film::factory()->create([
        'occurred_at' => '2026-09-19 20:00:00',
        'title' => 'Fall 2: Deadpoint',
        'rating' => 8,
        'meta' => ['year' => 2026, 'runtime' => 98],
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($film);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain('CINEMA TICKET')
        ->and($txt)->toContain('ADMIT ONE')
        ->and($txt)->toContain('FILM  : Fall 2: Deadpoint')
        ->and($txt)->toContain('DATE  : 19 Sep 2026')
        ->and($txt)->toContain('RATED: 8 out of 10')
        ->and($txt)->toContain('YEAR  : 2026')
        ->and($txt)->toContain('RUNTIME: 1h 38m')
        ->and($txt)->toContain('No. '.str_pad((string) $film->id, 10, '0', STR_PAD_LEFT))
        ->and($txt)->toContain('TAYLORDRAYSON');
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
        ->and($txt)->toContain('DATE  :');
});

it('renders the same barcode for the same film every time', function () {
    $film = Film::factory()->create(['occurred_at' => '2026-09-13 20:00:00', 'status' => 'published']);

    $data = ExportPresenter::for($film);
    $first = Formats::find($data, ExportFormat::Txt)->render($data);
    $second = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($first)->toBe($second);
});
