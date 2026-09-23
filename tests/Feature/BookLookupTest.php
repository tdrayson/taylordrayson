<?php

use App\Models\User;
use App\Queries\Lookups\BookCoverLookup;
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
        ->and($option['fill'])->toBe([
            'title' => 'How to Win Friends and Influence People',
            'meta.author' => 'Dale Carnegie',
            'overview' => 'Simple and timeless tools for success.',
            'cover' => [['id' => 'url:https://assets.hardcover.app/edition-1.jpeg', 'name' => 'Cover', 'url' => 'https://assets.hardcover.app/edition-1.jpeg']],
            'tags' => ['Self-Help', 'Psychology'],
        ]);
});

it('keeps the search results when the edition lookup fails', function () {
    Saloon::fake([hardcoverSearch(), MockResponse::make(['errors' => [['message' => 'boom']]], 200)]);

    $option = app(BookLookup::class)('how to win friends')[0];

    expect($option['fill'])->toMatchArray(['title' => 'How to Win Friends and Influence People', 'meta.author' => 'Dale Carnegie', 'cover' => [['id' => 'url:https://assets.hardcover.app/book.jpeg', 'name' => 'Cover', 'url' => 'https://assets.hardcover.app/book.jpeg']]])
        ->and($option)->not->toHaveKeys(['image', 'suggestions']);
});

it('lists unique edition covers for the best match, capped at twenty, with the book cover as a fallback', function () {
    $editions = [...editionCovers(24), ['image' => ['url' => 'https://assets.hardcover.app/edition-1.jpeg']], ['image' => null]];
    Saloon::fake([hardcoverSearch(), hardcoverBooks($editions)]);

    $covers = app(BookCoverLookup::class)('How to Win Friends Dale Carnegie');

    $urls = array_column(array_map(fn ($edition) => $edition->toArray(), $covers), 'cover');

    expect($covers)->toHaveCount(20)
        ->and($urls[0])->toBe('https://assets.hardcover.app/edition-1.jpeg')
        ->and(array_unique($urls))->toBe($urls);

    Saloon::fake([hardcoverSearch(), hardcoverBooks([])]);

    expect(app(BookCoverLookup::class)('How to Win Friends')[0]->toArray())
        ->toBe(['cover' => 'https://assets.hardcover.app/book.jpeg', 'isbn' => null, 'year' => null]);
});

it('returns no covers for a blank query or a failed search', function () {
    expect(app(BookCoverLookup::class)('  '))->toBe([]);

    Saloon::fake([MockResponse::make(['errors' => [['message' => 'boom']]], 200)]);

    expect(app(BookCoverLookup::class)('How to Win Friends'))->toBe([]);
});

it('serves covers from the lookup endpoint', function () {
    Saloon::fake([hardcoverSearch(), hardcoverBooks(editionCovers(2))]);

    $this->actingAs(User::factory()->create())
        ->getJson('/lookup/book-covers?q=How+to+Win+Friends')
        ->assertOk()
        ->assertJsonPath('data.0.cover', 'https://assets.hardcover.app/edition-1.jpeg');
});

it('gives each edition its own ISBN and year, preferring the ISBN-13', function () {
    Saloon::fake([hardcoverSearch(), hardcoverBooks([
        ['isbn_13' => '9780141301143', 'isbn_10' => '0141301147', 'release_year' => null, 'release_date' => '1959-01-01', 'image' => ['url' => 'https://assets.hardcover.app/puffin.jpeg']],
        ['isbn_13' => null, 'isbn_10' => '0553152890', 'release_year' => 1975, 'release_date' => null, 'image' => ['url' => 'https://assets.hardcover.app/bantam.jpeg']],
        ['isbn_13' => '', 'isbn_10' => null, 'release_year' => null, 'release_date' => null, 'image' => ['url' => 'https://assets.hardcover.app/unknown.jpeg']],
    ])]);

    $editions = array_map(fn ($edition) => $edition->toArray(), app(BookCoverLookup::class)('Danny the Champion of the World'));

    expect(array_slice($editions, 0, 3))->toBe([
        ['cover' => 'https://assets.hardcover.app/puffin.jpeg', 'isbn' => '9780141301143', 'year' => 1959],
        ['cover' => 'https://assets.hardcover.app/bantam.jpeg', 'isbn' => '0553152890', 'year' => 1975],
        ['cover' => 'https://assets.hardcover.app/unknown.jpeg', 'isbn' => null, 'year' => null],
    ]);
});
