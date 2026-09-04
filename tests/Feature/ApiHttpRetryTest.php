<?php

use Illuminate\Support\Facades\Http;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

/*
 * The retry policy every third-party client goes through, as `Http::api()`.
 * A rate limit is temporary, so treating it as a permanent failure loses the
 * run for no reason.
 */

it('retries a 429 and returns the eventual success', function () {
    Saloon::fake(['example.test/*' => mockSequence([
        MockResponse::make('rate limited', 429),
        MockResponse::make(['ok' => true], 200),
    ]),
    ]);

    $response = Http::api()->get('https://example.test/thing');

    expect($response->successful())->toBeTrue()
        ->and($response->json('ok'))->toBeTrue();

    Saloon::assertSentCount(2);
});

it('retries a 503', function () {
    Saloon::fake(['example.test/*' => mockSequence([
        MockResponse::make('down', 503),
        MockResponse::make(['ok' => true], 200),
    ]),
    ]);

    expect(Http::api()->get('https://example.test/thing')->successful())->toBeTrue();

    Saloon::assertSentCount(2);
});

// The client's own handling stays in charge: every caller checks failed() or
// throws its own message, and a thrown RequestException would bypass that.
it('gives up after three attempts and returns the response rather than throwing', function () {
    Saloon::fake(['example.test/*' => MockResponse::make('rate limited', 429)]);

    $response = Http::api()->get('https://example.test/thing');

    expect($response->status())->toBe(429)
        ->and($response->failed())->toBeTrue();

    Saloon::assertSentCount(3);
});

it('does not retry a status that will not fix itself', function (int $status) {
    Saloon::fake(['example.test/*' => MockResponse::make('no', $status)]);

    expect(Http::api()->get('https://example.test/thing')->status())->toBe($status);

    Saloon::assertSentCount(1);
})->with([401, 403, 404, 422]);

it('identifies itself on every request, not just those going through api()', function () {
    Saloon::fake(['' => MockResponse::make('', 200)]);

    Http::get('https://example.test/thing');

    Saloon::assertSent(fn ($request, $response) => str_contains($request->header('User-Agent')[0] ?? '', config('app.name')));
});
