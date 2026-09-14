<?php

use App\Queries\Lookups\BookLookup;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(fn () => config()->set('services.hardcover.key', 'test-hardcover-key'));

function hardcoverSearch(): MockResponse
{
    return MockResponse::make(['data' => ['search' => ['results' => ['hits' => [
        ['document' => [
            'id' => '427812',
            'title' => 'How to Win Friends and Influence People',
            'subtitle' => 'Die Kunst, beliebt und einflussreich zu werden',
            'author_names' => ['Dale Carnegie'],
            'release_year' => 1913,
            'pages' => 288,
            'image' => ['url' => 'https://assets.hardcover.app/book.jpeg'],
        ]],
    ]]]]]);
}

function hardcoverEditions(array $book = []): MockResponse
{
    return MockResponse::make(['data' => ['books' => [[
        'id' => 427812,
        'description' => 'Simple and timeless tools for success.',
        'image' => ['url' => 'https://assets.hardcover.app/book.jpeg'],
        'cached_tags' => ['Genre' => [
            ['tag' => 'Self-Help', 'count' => 4],
            ['tag' => 'Psychology', 'count' => 4],
            ['tag' => '1735854455972', 'count' => 1],
        ]],
        'default_physical_edition' => ['subtitle' => null, 'pages' => 276, 'isbn_13' => '9780671027032', 'image' => ['url' => 'https://assets.hardcover.app/paperback.jpeg']],
        'default_ebook_edition' => ['subtitle' => null, 'pages' => 288, 'isbn_13' => '9781443433167', 'image' => ['url' => 'https://assets.hardcover.app/ebook.jpeg']],
        ...$book,
    ]]]]);
}

it('fills from the default physical edition, not the book record', function () {
    Saloon::fake([hardcoverSearch(), hardcoverEditions()]);

    $option = app(BookLookup::class)('how to win friends')[0];

    expect($option['detail'])->toBe('Dale Carnegie')
        ->and($option['fill'])->toBe([
            'title' => 'How to Win Friends and Influence People',
            'meta.author' => 'Dale Carnegie',
            'meta.isbn' => '9780671027032',
            'pages' => 276,
            'overview' => 'Simple and timeless tools for success.',
            'cover' => [['id' => 'url:https://assets.hardcover.app/paperback.jpeg', 'name' => 'Cover', 'url' => 'https://assets.hardcover.app/paperback.jpeg']],
            'tags' => ['Self-Help', 'Psychology'],
        ]);
});

it('falls back to the ebook edition when there is no physical one', function () {
    Saloon::fake([hardcoverSearch(), hardcoverEditions(['default_physical_edition' => null])]);

    expect(app(BookLookup::class)('how to win friends')[0]['fill'])
        ->toMatchArray(['meta.isbn' => '9781443433167', 'pages' => 288]);
});

it('keeps the search results when the edition lookup fails', function () {
    Saloon::fake([hardcoverSearch(), MockResponse::make(['errors' => [['message' => 'boom']]], 200)]);

    expect(app(BookLookup::class)('how to win friends')[0]['fill'])
        ->toMatchArray(['title' => 'How to Win Friends and Influence People', 'pages' => 288])
        ->not->toHaveKey('meta.subtitle');
});
