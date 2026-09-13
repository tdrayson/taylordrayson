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

it('names the book by its author', function () {
    $book = Book::factory()->make([
        'title' => 'Piranesi',
        'rating' => 9,
        'meta' => ['author' => 'Susanna Clarke'],
    ]);

    $card = CardPresenter::for($book);

    expect($card->title)->toBe('Piranesi')
        ->and($card->subtitle)->toBe('I read this book by Susanna Clarke and rated it 9/10.');
});
