<?php

namespace App\Queries\Lookups;

use App\Data\BookEdition;
use Illuminate\Support\Collection;

/**
 * The editions a Hardcover book has been printed with, most-read first, one per cover.
 */
final class BookCovers
{
    private const MAX = 20;

    /**
     * @param  array<string, mixed>  $document  A search hit.
     * @param  array<string, mixed>  $details  The same book from Client::booksWithEditions().
     * @return list<BookEdition>
     */
    public static function from(array $document, array $details): array
    {
        $fallback = data_get($details, 'image.url') ?? data_get($document, 'image.url');

        return Collection::make((array) data_get($details, 'editions', []))
            ->map(fn (mixed $edition): ?BookEdition => is_array($edition) ? self::edition($edition) : null)
            ->push(is_string($fallback) && $fallback !== '' ? new BookEdition($fallback) : null)
            ->filter()
            ->unique(fn (BookEdition $edition): string => $edition->cover)
            ->take(self::MAX)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $edition  One of Client::booksWithEditions()' editions.
     * @return BookEdition|null Null for an edition with no cover to show.
     */
    private static function edition(array $edition): ?BookEdition
    {
        $cover = data_get($edition, 'image.url');

        if (! is_string($cover) || $cover === '') {
            return null;
        }

        $isbn = collect([$edition['isbn_13'] ?? null, $edition['isbn_10'] ?? null])
            ->first(fn (mixed $isbn): bool => is_string($isbn) && trim($isbn) !== '');
        $year = $edition['release_year'] ?? substr((string) ($edition['release_date'] ?? ''), 0, 4);
        $pages = $edition['pages'] ?? null;

        return new BookEdition(
            cover: $cover,
            isbn: $isbn === null ? null : trim($isbn),
            year: is_numeric($year) && (int) $year > 0 ? (int) $year : null,
            pages: is_numeric($pages) && (int) $pages > 0 ? (int) $pages : null,
        );
    }
}
