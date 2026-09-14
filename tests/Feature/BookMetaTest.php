<?php

use App\Models\Book;

it('round-trips keys it does not name, rather than dropping them on save', function () {
    // `plex` nested under `ids` is not a real book key, but stands in here for
    // "something no field on the DTO names". A strict DTO would delete it the
    // first time the row was re-saved.
    $book = Book::factory()->create(['meta' => [
        'author' => 'Pat Barker',
        'isbn' => '9780241983201',
        'ids' => ['trakt' => 5, 'plex' => ['guid' => 'abc']],
    ]]);

    $book->fresh()->touch();

    expect($book->fresh()->meta->toArray())->toBe([
        'author' => 'Pat Barker',
        'isbn' => '9780241983201',
        'ids' => ['trakt' => 5, 'plex' => ['guid' => 'abc']],
    ]);
});

it('keeps a subtitle as a named meta key', function () {
    $book = Book::factory()->create(['meta' => ['author' => 'Dale Carnegie', 'subtitle' => 'The classic']]);

    expect($book->fresh()->meta->subtitle)->toBe('The classic')
        ->and($book->fresh()->meta->extra)->not->toHaveKey('subtitle');
});

it('stores reading progress on the book itself', function () {
    $book = Book::factory()->create([
        'status' => 'draft',
        'occurred_at' => null,
        'progress_percent' => 42.5,
        'current_page' => 122,
        'pages' => 288,
        'progressed_at' => '2026-09-14 08:00:00',
        'overview' => 'A synopsis.',
    ]);

    $fresh = $book->fresh();

    expect($fresh->progress_percent)->toBe(42.5)
        ->and($fresh->current_page)->toBe(122)
        ->and($fresh->pages)->toBe(288)
        ->and($fresh->progressed_at->format('Y-m-d H:i:s'))->toBe('2026-09-14 08:00:00')
        ->and($fresh->overview)->toBe('A synopsis.');
});

it('tags a book', function () {
    $book = Book::factory()->create();
    $book->syncTagNames(['Self-Help', 'Psychology']);

    expect($book->fresh()->tagNames())->toEqualCanonicalizing(['Self-Help', 'Psychology']);
});
