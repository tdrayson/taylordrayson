<?php

namespace App\Queries\Books;

use App\Models\Book;
use App\Support\BookCompleteness;
use App\Support\BookProgress;
use Illuminate\Support\Arr;

/**
 * The line /drafts shows beside a book: how far in, and what it still needs.
 */
final class BookDraftDetail
{
    public function __invoke(Book $book): ?string
    {
        $missing = BookCompleteness::forBook($book);

        $parts = array_filter([
            $book->progress_percent === null ? null : BookProgress::display($book->progress_percent).'%',
            $missing === [] ? null : 'needs '.Arr::join($missing, ', ', ' and '),
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }
}
