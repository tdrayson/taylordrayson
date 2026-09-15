<?php

namespace App\Queries\Lookups;

use Illuminate\Support\Collection;

/**
 * The covers a Hardcover book has been printed with, most-read first.
 */
final class BookCovers
{
    private const MAX = 20;

    /**
     * @param  array<string, mixed>  $document  A search hit.
     * @param  array<string, mixed>  $details  The same book from Client::booksWithEditions().
     * @return list<string>
     */
    public static function from(array $document, array $details): array
    {
        return Collection::make((array) data_get($details, 'editions', []))
            ->map(fn (mixed $edition): mixed => data_get($edition, 'image.url'))
            ->push(data_get($details, 'image.url') ?? data_get($document, 'image.url'))
            ->filter(fn (mixed $url): bool => is_string($url) && $url !== '')
            ->unique()
            ->take(self::MAX)
            ->values()
            ->all();
    }
}
