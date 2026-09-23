<?php

namespace App\Queries\Hub\Checks;

use App\Data\Hub\AttentionItem;
use App\Enums\EntryStatus;
use App\Models\Book;
use App\Support\BookCompleteness;

/**
 * Books the Kindle pushed as drafts, still short of what publishing needs.
 * Clears when the missing fields are filled in.
 */
final class IncompleteBooks implements Check
{
    /**
     * @return list<AttentionItem>
     */
    public function items(): array
    {
        $books = Book::query()
            ->where('status', EntryStatus::Draft)
            ->get()
            ->filter(fn (Book $book): bool => BookCompleteness::forBook($book) !== []);

        if ($books->isEmpty()) {
            return [];
        }

        // The shared definition, so this cannot drift from the editor, /drafts
        // and the Kindle sync.
        $missing = BookCompleteness::sentence(
            $books->flatMap(fn (Book $book): array => BookCompleteness::forBook($book))->unique()->values()->all()
        );

        return [new AttentionItem(
            id: 'incomplete-books',
            kind: 'draft',
            icon: 'BookOpen01Icon',
            title: $books->count() === 1
                ? "There's a book waiting on {$missing}"
                : "There are {$books->count()} books waiting on {$missing}",
            detail: 'Sideloaded, so nothing could be looked up.',
            body: null,
            age: $books->max('updated_at')->diffForHumans(),
            href: '/drafts',
        )];
    }
}
