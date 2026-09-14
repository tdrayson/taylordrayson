<?php

use App\Models\Book;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Storage::fake(config('media-library.disk_name')));

function readingBook(array $attributes, bool $cover = true): Book
{
    $book = Book::factory()->create([
        'status' => 'draft',
        'occurred_at' => null,
        'meta' => ['author' => 'James Clear'],
        ...$attributes,
    ]);

    if ($cover) {
        $book->addMediaFromString(fakeJpeg())->usingFileName('cover.jpg')->toMediaCollection('cover');
    }

    return $book;
}

it('shows the complete book read most recently', function () {
    readingBook(['title' => 'Older', 'progress_percent' => 80.0, 'progressed_at' => '2026-09-10 20:00:00']);
    readingBook(['title' => 'Atomic Habits', 'progress_percent' => 42.9, 'progressed_at' => '2026-09-13 20:00:00']);
    readingBook(['title' => 'Unmatched', 'progress_percent' => 5.0, 'progressed_at' => '2026-09-14 08:00:00', 'meta' => []], cover: false);

    $this->get('/now')->assertInertia(fn (Assert $page) => $page
        ->where('reading.title', 'Atomic Habits')
        ->where('reading.author', 'James Clear')
        ->where('reading.percent', 42)
        ->whereType('reading.cover', 'string')
    );
});

it('shows nothing when no book is in progress', function () {
    Book::factory()->create(['status' => 'published', 'progress_percent' => 100.0, 'progressed_at' => now()]);

    $this->get('/now')->assertInertia(fn (Assert $page) => $page->where('reading', null));
});
