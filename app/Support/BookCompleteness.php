<?php

namespace App\Support;

use App\Models\Book;
use Illuminate\Support\Arr;

/**
 * What a book must have before it can publish: a title, an author and a cover.
 * The one definition, shared by the Kindle sync, the editor, /drafts and /now.
 */
final class BookCompleteness
{
    private const PHRASES = ['title' => 'a title', 'author' => 'an author', 'cover' => 'a cover'];

    /**
     * @return list<string>
     */
    public static function missing(?string $title, ?string $author, bool $hasCover): array
    {
        return array_keys(array_filter([
            'title' => blank($title),
            'author' => blank($author),
            'cover' => ! $hasCover,
        ]));
    }

    /**
     * @return list<string>
     */
    public static function forBook(Book $book): array
    {
        return self::missing($book->title, $book->meta->author, $book->exists && $book->hasMedia('cover'));
    }

    /**
     * @param  list<string>  $missing
     */
    public static function sentence(array $missing): string
    {
        return Arr::join(array_map(fn (string $key): string => self::PHRASES[$key], $missing), ', ', ' and ');
    }
}
