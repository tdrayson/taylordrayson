<?php

namespace App\Actions\Books;

use App\Models\Media;

class UpdateBook
{
    /**
     * Meta is merged, never replaced, so editing the author does not drop the
     * ISBN alongside it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Media $book, array $attributes): Media
    {
        if (array_key_exists('meta', $attributes)) {
            $attributes['meta'] = [...$book->meta ?? [], ...$attributes['meta']];
        }

        $book->fill($attributes)->save();

        return $book->refresh();
    }
}
