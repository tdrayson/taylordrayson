<?php

namespace App\Actions\Books;

use App\Models\Media;

class DeleteBook
{
    public function __invoke(Media $book): void
    {
        $book->delete();
    }
}
