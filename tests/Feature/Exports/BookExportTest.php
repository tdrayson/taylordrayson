<?php

use App\Models\Book;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('publishes a book as labelled fields in order', function () {
    $book = Book::factory()->create([
        'occurred_at' => '2026-09-13 22:00:00',
        'started_at' => '2026-08-01 09:00:00',
        'title' => 'Project Hail Mary',
        'rating' => 9,
        'meta' => ['author' => 'Andy Weir'],
        'pages' => 476,
        'progress_percent' => 100,
        'status' => 'published',
    ]);

    $export = ExportPresenter::for($book);

    expect(array_map(fn ($f) => $f->key, $export->fields))
        ->toBe(['book', 'author', 'rating', 'pages', 'progress', 'started'])
        ->and($export->field('book')->display)->toBe('Project Hail Mary')
        ->and($export->field('author')->display)->toBe('Andy Weir')
        ->and($export->field('rating')->display)->toBe('9 out of 10')
        ->and($export->field('pages')->display)->toBe('476')
        ->and($export->field('progress')->display)->toBe('100%')
        ->and($export->field('started')->display)->toBe('1 Aug 2026')
        ->and($export->field('started')->raw)->toBe('2026-08-01T09:00:00+00:00');
});

it('rounds progress to a whole percent for display while raw keeps the exact float', function () {
    $book = Book::factory()->create([
        'occurred_at' => '2026-09-13 22:00:00',
        'progress_percent' => 12.287,
        'status' => 'published',
    ]);

    $field = ExportPresenter::for($book)->field('progress');

    expect($field->display)->toBe('12%')
        ->and($field->raw)->toBe(12.287);
});

it('offers neither geojson nor ics for a book', function () {
    $book = Book::factory()->create(['occurred_at' => '2026-09-13 22:00:00', 'status' => 'published']);

    $formats = array_keys(Formats::for(ExportPresenter::for($book)));

    expect($formats)->not->toContain('geojson')
        ->and($formats)->not->toContain('ics');
});

it('never leaks an id, a timestamp or a password', function () {
    $book = Book::factory()->create(['occurred_at' => '2026-09-13 22:00:00', 'status' => 'published']);

    $json = json_encode(ExportPresenter::for($book)->toArray());

    expect($json)->not->toContain('password')
        ->not->toContain('created_at')
        ->not->toContain('updated_at');
});
