<?php

namespace App\Actions\Books;

use App\Models\Book;

class UpdateBook
{
    /**
     * Meta is merged, never replaced, so editing the author does not drop the
     * ISBN alongside it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Book $book, array $attributes): Book
    {
        if (array_key_exists('meta', $attributes)) {
            $attributes['meta'] = $book->meta->merge($attributes['meta']);
        }

        if (array_key_exists('tags', $attributes)) {
            $book->syncTagNames($attributes['tags']);
            unset($attributes['tags']);
        }

        $book->fill($attributes)->save();

        return $book->refresh();
    }
}
