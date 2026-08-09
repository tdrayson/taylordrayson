<?php

namespace App\Actions\Books;

use App\Enums\MediaType;
use App\Models\Media;

class CreateBook
{
    /**
     * A book is a Media row with type = book. Author, year and ISBN have no
     * columns of their own, so they are merged into meta rather than assigned
     * over it: a later field must not wipe the ones already stored.
     *
     * @param  array{title: string, occurred_at?: string|null, rating?: int|null, timezone?: string|null, meta?: array<string, mixed>}  $attributes
     */
    public function __invoke(array $attributes): Media
    {
        return Media::create([
            'title' => $attributes['title'],
            'type' => MediaType::Book,
            'occurred_at' => $attributes['occurred_at'] ?? now(),
            'rating' => $attributes['rating'] ?? null,
            'timezone' => $attributes['timezone'] ?? config('app.home_timezone'),
            'source' => 'manual',
            'meta' => $attributes['meta'] ?? [],
        ]);
    }
}
