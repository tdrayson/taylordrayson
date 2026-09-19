<?php

use App\Enums\ExportFormat;
use App\Models\Book;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('prints a book as its library card, with progress sized as a bar from raw', function () {
    $book = Book::factory()->create([
        'occurred_at' => '2026-09-13 20:00:00',
        'started_at' => '2026-09-02 19:25:20',
        'title' => 'How to Win Friends and Influence People',
        'meta' => ['author' => 'Dale Carnegie'],
        'pages' => 291,
        'progress_percent' => 60,
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($book);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain('How to Win Friends and Influence People')
        ->and($txt)->toContain('Dale Carnegie')
        ->and($txt)->toContain('291')
        ->and($txt)->toContain('60%')
        ->and($txt)->toContain(str_repeat('#', 15))
        ->and($txt)->toContain('2 September 2026');
});

it('omits pages and progress rows when a book carries neither', function () {
    $book = Book::factory()->create([
        'occurred_at' => '2026-09-13 20:00:00',
        'pages' => null,
        'progress_percent' => null,
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($book);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->not->toContain('Pages')
        ->and($txt)->not->toContain('Progress');
});
