<?php

namespace App\Actions\Books;

use App\Enums\EntryStatus;
use App\Models\Book;

class CreateBook
{
    /**
     * @param  array{title: string, occurred_at?: string|null, started_at?: string|null, rating?: int|null, timezone?: string|null, meta?: array<string, mixed>, status?: string, password?: string|null}  $attributes
     */
    public function __invoke(array $attributes): Book
    {
        return Book::create([
            'title' => $attributes['title'],
            'occurred_at' => $attributes['occurred_at'] ?? null,
            'started_at' => $attributes['started_at'] ?? null,
            'rating' => $attributes['rating'] ?? null,
            'timezone' => $attributes['timezone'] ?? config('app.home_timezone'),
            'source' => 'manual',
            'meta' => $attributes['meta'] ?? [],
            'status' => $attributes['status'] ?? EntryStatus::Published,
            'password' => $attributes['password'] ?? null,
        ]);
    }
}
