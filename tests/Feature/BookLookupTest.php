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
            'isbns' => ['9780671027032'],
            'image' => ['url' => 'https://assets.hardcover.app/book.jpeg'],
        ]],
    ]]]]]);
}

function hardcoverBooks(array $editions): MockResponse
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
        'editions' => $editions,
    ]]]]);
}

function editionCovers(int $count): array
{
    return array_map(fn (int $n): array => ['image' => ['url' => "https://assets.hardcover.app/edition-{$n}.jpeg"]], range(1, $count));
}

it('fills book-level details and the most-read cover, never edition details', function () {
    Saloon::fake([hardcoverSearch(), hardcoverBooks(editionCovers(2))]);

    $option = app(BookLookup::class)('how to win friends')[0];

    expect($option['detail'])->toBe('Dale Carnegie')
        ->and($option['image'])->toBe('https://assets.hardcover.app/edition-1.jpeg')
        ->and($option['fill'])->toBe([
            'title' => 'How to Win Friends and Influence People',
            'meta.author' => 'Dale Carnegie',
            'overview' => 'Simple and timeless tools for success.',
            'cover' => [['id' => 'url:https://assets.hardcover.app/edition-1.jpeg', 'name' => 'Cover', 'url' => 'https://assets.hardcover.app/edition-1.jpeg']],
            'tags' => ['Self-Help', 'Psychology'],
        ]);
});

it('suggests unique edition covers, capped at twelve, with the book cover as a fallback', function () {
    $editions = [...editionCovers(14), ['image' => ['url' => 'https://assets.hardcover.app/edition-1.jpeg']], ['image' => null]];
    Saloon::fake([hardcoverSearch(), hardcoverBooks($editions)]);

    $covers = app(BookLookup::class)('how to win friends')[0]['suggestions']['cover'];

    expect($covers)->toHaveCount(12)
        ->and($covers[0])->toBe('https://assets.hardcover.app/edition-1.jpeg')
        ->and(array_unique($covers))->toBe($covers);

    Saloon::fake([hardcoverSearch(), hardcoverBooks([])]);

    expect(app(BookLookup::class)('how to win friends')[0]['suggestions']['cover'])
        ->toBe(['https://assets.hardcover.app/book.jpeg']);
});

it('keeps the search results when the edition lookup fails', function () {
    Saloon::fake([hardcoverSearch(), MockResponse::make(['errors' => [['message' => 'boom']]], 200)]);

    $option = app(BookLookup::class)('how to win friends')[0];

    expect($option['fill'])->toMatchArray(['title' => 'How to Win Friends and Influence People', 'meta.author' => 'Dale Carnegie'])
        ->and($option['suggestions']['cover'])->toBe(['https://assets.hardcover.app/book.jpeg']);
});
