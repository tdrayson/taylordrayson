<?php

use App\Models\Book;
use App\Support\BookCompleteness;
use App\Support\BookProgress;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake(config('media-library.disk_name')));

it('names what a book is missing, in order', function () {
    expect(BookCompleteness::missing('Atomic Habits', null, false))->toBe(['author', 'cover'])
        ->and(BookCompleteness::missing(' ', 'James Clear', true))->toBe(['title'])
        ->and(BookCompleteness::missing('Atomic Habits', 'James Clear', true))->toBe([]);
});

it('reads a saved book, cover included', function () {
    $book = Book::factory()->create(['meta' => ['author' => 'James Clear']]);

    expect(BookCompleteness::forBook($book))->toBe(['cover']);

    $book->addMediaFromString(fakeJpeg())->usingFileName('cover.jpg')->toMediaCollection('cover');

    expect(BookCompleteness::forBook($book->fresh()))->toBe([]);
});

it('phrases what is missing as part of a sentence', function () {
    expect(BookCompleteness::sentence(['author', 'cover']))->toBe('an author and a cover')
        ->and(BookCompleteness::sentence(['title', 'author', 'cover']))->toBe('a title, an author and a cover');
});

it('derives percent from a page and never passes 100', function () {
    expect(BookProgress::fromPage(122, 288))->toBe(42.361)
        ->and(BookProgress::fromPage(300, 288))->toBe(100.0)
        ->and(BookProgress::round(9.71696000000000026))->toBe(9.717)
        ->and(BookProgress::display(99.9))->toBe(99);
});
