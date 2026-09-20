<?php

use App\Enums\ExportFormat;
use App\Models\Book;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('falls back to an aligned table for a type with no sheet', function () {
    $book = Book::factory()->create(['occurred_at' => '2026-09-13 22:00:00', 'pages' => 476, 'status' => 'published']);
    $data = ExportPresenter::for($book);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain('PAGES')
        ->and($txt)->toContain('476');
});

it('does not print a trail of other formats', function () {
    $data = ExportPresenter::for(krkToLgw());
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->not->toContain('.json')
        ->and($txt)->not->toContain('.md')
        ->and($txt)->not->toContain('.mf2');
});

it('prints only display strings, never a raw value, for a flight sheet', function () {
    $data = ExportPresenter::for(krkToLgw());
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    // 1409785 is the raw metres; the sheet must print "876 miles".
    expect($txt)->not->toContain('1409785');
});
