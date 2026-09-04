<?php

use App\Exceptions\HardcoverException;
use App\Services\Hardcover;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.hardcover.key', 'test-hardcover-key');
});

it('posts a GraphQL search with the bearer token', function () {
    Http::fake([
        'api.hardcover.app/v1/graphql' => Http::response([
            'data' => [
                'search' => [
                    'error' => null,
                    'page' => 1,
                    'per_page' => 25,
                    'query' => 'atomic habits',
                    'query_type' => 'Book',
                    'results' => [
                        'found' => 1,
                        'hits' => [
                            [
                                'document' => [
                                    'id' => '428023',
                                    'title' => 'Atomic Habits',
                                    'author_names' => ['James Clear'],
                                    'slug' => 'atomic-habits',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $search = app(Hardcover::class)->search('atomic habits');

    expect($search['query'])->toBe('atomic habits')
        ->and($search['query_type'])->toBe('Book')
        ->and($search['results']['hits'][0]['document']['title'])->toBe('Atomic Habits');

    Http::assertSent(function ($request, $response) {
        $body = $request->data();

        return $request->url() === 'https://api.hardcover.app/v1/graphql'
            && $request->hasHeader('Authorization', 'Bearer test-hardcover-key')
            && str_contains($body['query'], 'search(query: $query)')
            && $body['variables']['query'] === 'atomic habits';
    });
});

it('flattens search hits into book documents', function () {
    Http::fake([
        'api.hardcover.app/v1/graphql' => Http::response([
            'data' => [
                'search' => [
                    'error' => null,
                    'page' => 1,
                    'per_page' => 25,
                    'query' => 'atomic habits',
                    'query_type' => 'Book',
                    'results' => [
                        'hits' => [
                            ['document' => ['id' => '1', 'title' => 'Atomic Habits']],
                            ['document' => ['id' => '2', 'title' => 'Atomic Habits Workbook']],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $documents = app(Hardcover::class)->searchDocuments('atomic habits');

    expect($documents)->toHaveCount(2)
        ->and($documents[0]['title'])->toBe('Atomic Habits')
        ->and($documents[1]['id'])->toBe('2');
});

it('strips a leading Bearer prefix from the configured key', function () {
    config()->set('services.hardcover.key', 'Bearer already-prefixed');

    Http::fake([
        'api.hardcover.app/v1/graphql' => Http::response([
            'data' => ['search' => ['error' => null, 'results' => ['hits' => []]]],
        ]),
    ]);

    app(Hardcover::class)->search('test');

    Http::assertSent(fn ($request, $response) => $request->hasHeader('Authorization', 'Bearer already-prefixed'));
});

it('throws when the API key is missing', function () {
    config()->set('services.hardcover.key', null);

    expect(fn () => app(Hardcover::class)->search('atomic habits'))
        ->toThrow(HardcoverException::class, 'HARDCOVER_API_KEY is not configured.');
});

it('throws when the HTTP request fails', function () {
    Http::fake([
        'api.hardcover.app/v1/graphql' => Http::response('nope', 500),
    ]);

    expect(fn () => app(Hardcover::class)->search('atomic habits'))
        ->toThrow(HardcoverException::class, 'failed with status 500');
});

it('throws when GraphQL returns an errors payload', function () {
    Http::fake([
        'api.hardcover.app/v1/graphql' => Http::response([
            'errors' => [
                ['message' => 'Unable to verify token'],
            ],
        ]),
    ]);

    expect(fn () => app(Hardcover::class)->query('{ me { username } }'))
        ->toThrow(HardcoverException::class, 'Unable to verify token');
});

it('retries a 429 response and resolves to the eventual body', function () {
    Http::fake([
        'api.hardcover.app/v1/graphql' => mockSequence([
            MockResponse::make('rate limited', 429),
        ]),
    ]);

    $documents = app(Hardcover::class)->searchDocuments('dune');

    expect($documents)->toHaveCount(1)
        ->and($documents[0]['title'])->toBe('Dune');

    Http::assertSentCount(2);
});
