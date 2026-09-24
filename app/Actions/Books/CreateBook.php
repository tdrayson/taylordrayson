<?php

namespace App\Actions\Books;

use App\Enums\EntryStatus;
use App\Enums\Source;
use App\Models\Book;

class CreateBook
{
    /**
     * @param  array{title: string, occurred_at?: string|null, started_at?: string|null, rating?: int|null, timezone?: string|null, meta?: array<string, mixed>, status?: string, password?: string|null, progress_percent?: float|null, current_page?: int|null, pages?: int|null, progressed_at?: string|null, overview?: string|null, tags?: list<string>}  $attributes
     */
    public function __invoke(array $attributes): Book
    {
        $book = Book::create([
            'title' => $attributes['title'],
            'occurred_at' => $attributes['occurred_at'] ?? null,
            'started_at' => $attributes['started_at'] ?? null,
            'rating' => $attributes['rating'] ?? null,
            'timezone' => $attributes['timezone'] ?? config('app.home_timezone'),
            'source' => Source::Manual->value,
            'meta' => $attributes['meta'] ?? [],
            'status' => $attributes['status'] ?? EntryStatus::Published,
            'password' => $attributes['password'] ?? null,
            'progress_percent' => $attributes['progress_percent'] ?? null,
            'current_page' => $attributes['current_page'] ?? null,
            'pages' => $attributes['pages'] ?? null,
            'progressed_at' => $attributes['progressed_at'] ?? null,
            'overview' => $attributes['overview'] ?? null,
        ]);

        if (array_key_exists('tags', $attributes)) {
            $book->syncTagNames($attributes['tags']);
        }

        return $book;
    }
}
