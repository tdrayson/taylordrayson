<?php

use App\Data\ExportData;
use App\Data\ExportField;
use App\Data\ExportLink;
use App\Enums\ExportFormat;
use App\Enums\TimelineType;
use App\Models\Book;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('falls back to an aligned table for a type with no sheet', function () {
    $book = Book::factory()->create(['occurred_at' => '2026-09-13 22:00:00', 'pages' => 476, 'status' => 'published']);
    $data = ExportPresenter::for($book);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain('Pages')
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

it('renders a locked entry as header only, with no fields or links', function () {
    $data = new ExportData(
        type: TimelineType::Note,
        url: 'https://example.test/secret',
        title: 'A private note',
        summary: 'A summary that should not leak',
        occurred: null,
        fields: [ExportField::make('body_word_count', 'Word count', '42', 42)],
        links: [ExportLink::make('tag', 'Tag', 'Secret tag', 'https://example.test/tags/secret')],
        locked: true,
    );

    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain('A PRIVATE NOTE')
        ->and($txt)->not->toContain('Word count')
        ->and($txt)->not->toContain('42')
        ->and($txt)->not->toContain('Secret tag')
        ->and($txt)->not->toContain('should not leak');
});
