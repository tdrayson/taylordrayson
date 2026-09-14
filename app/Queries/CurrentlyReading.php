<?php

namespace App\Queries;

use App\Data\ReadingData;
use App\Enums\EntryStatus;
use App\Models\Book;
use App\Support\BookCompleteness;
use App\Support\BookProgress;

/**
 * The draft book read most recently, skipping any not yet matched. Drafts are
 * read on purpose: an unfinished book shows here and nowhere else.
 */
final class CurrentlyReading
{
    public function __invoke(): ?ReadingData
    {
        $book = Book::query()
            ->where('status', EntryStatus::Draft)
            ->whereNotNull('progressed_at')
            ->with('media')
            ->orderByDesc('progressed_at')
            ->get()
            ->first(fn (Book $book): bool => BookCompleteness::forBook($book) === []);

        if ($book === null) {
            return null;
        }

        return new ReadingData(
            title: $book->title,
            author: (string) $book->meta->author,
            cover: $book->optimisedUrl('cover'),
            percent: BookProgress::display((float) $book->progress_percent),
        );
    }
}
