<?php

namespace App\Actions\Books;

use App\Models\Book;

class DeleteBook
{
    public function __invoke(Book $book): void
    {
        $book->delete();
    }
}
