<?php

namespace App\Queries;

use App\Data\ReadingData;
use App\Enums\EntryStatus;
use App\Models\Book;
use App\Support\BookCompleteness;
use App\Support\BookProgress;

/**
 * The draft book read most recently, skipping any not yet matched. Drafts are
 * read on purpose: an unfinished book shows here and nowhere else. When
 * nothing is in progress, the most recently finished book stands in instead.
 */
final class CurrentlyReading
{
    public function __invoke(): ?ReadingData
    {
        $book = $this->book();

        if ($book === null) {
            return null;
        }

        $finished = $book->status !== EntryStatus::Draft;

        return new ReadingData(
            title: $book->title,
            author: (string) $book->meta->author,
            cover: $book->optimisedUrl('cover'),
            percent: $finished ? null : BookProgress::display((float) $book->progress_percent),
            finished: $finished,
        );
    }

    /** The book itself, for a caller (the /now export) that needs more than the widget's shaped payload. */
    public function book(): ?Book
    {
        $inProgress = Book::query()
            ->where('status', EntryStatus::Draft)
            ->whereNotNull('progressed_at')
            ->with('media')
            ->orderByDesc('progressed_at')
            ->get()
            ->first(fn (Book $book): bool => BookCompleteness::forBook($book) === []);

        // No completeness check on the fallback: an older published book may
        // lack a cover, and the widget already falls back to its own placeholder.
        return $inProgress ?? Book::query()->listed()->with('media')->orderByDesc('occurred_at')->first();
    }
}
