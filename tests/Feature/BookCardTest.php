<?php

use App\Enums\TimelineType;
use App\Models\Book;
use App\Presenters\CardPresenter;

it('renders a card for a book', function () {
    $book = Book::factory()->create(['title' => 'Test Title', 'meta' => []]);

    $card = CardPresenter::for($book);

    expect($card->type)->toBe(TimelineType::Book)
        ->and($card->title)->toBe('Test Title');
});

it('names the author as the owner of the book, with the overview as its summary', function (?string $author, ?int $rating, string $expected) {
    $book = Book::factory()->make([
        'title' => 'Project Hail Mary',
        'rating' => $rating,
        'overview' => 'Ryland Grace is the sole survivor.',
        'meta' => ['author' => $author],
    ]);

    $card = CardPresenter::for($book);

    expect($card->subtitle)->toBe($expected)
        ->and($card->summary)->toBe('Ryland Grace is the sole survivor.');
})->with([
    'rated, with author' => ['Andy Weir', 9, "I read Andy Weir's book and rated it 9/10."],
    'unrated' => ['Andy Weir', null, "I read Andy Weir's book."],
    'no author' => [null, 9, 'I read this book and rated it 9/10.'],
]);
