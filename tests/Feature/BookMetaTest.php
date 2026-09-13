<?php

use App\Models\Book;

it('round-trips keys it does not name, rather than dropping them on save', function () {
    // `plex` nested under `ids` is not a real book key, but stands in here for
    // "something no field on the DTO names". A strict DTO would delete it the
    // first time the row was re-saved.
    $book = Book::factory()->create(['meta' => [
        'author' => 'Pat Barker',
        'isbn' => '9780241983201',
        'ids' => ['trakt' => 5, 'plex' => ['guid' => 'abc']],
    ]]);

    $book->fresh()->touch();

    expect($book->fresh()->meta->toArray())->toBe([
        'author' => 'Pat Barker',
        'isbn' => '9780241983201',
        'ids' => ['trakt' => 5, 'plex' => ['guid' => 'abc']],
    ]);
});
