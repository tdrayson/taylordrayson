<?php

use App\Services\Rovi;
use Carbon\CarbonImmutable;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function () {
    config([
        'services.rovi.key' => 'test-key',
        'services.rovi.base_url' => 'https://rovi.test/personalApi',
    ]);
});

/** The Rovi envelope around a data payload. */
function roviEnvelope(mixed $data, ?string $nextCursor = null): array
{
    return [
        'data' => $data,
        'paging' => ['nextCursor' => $nextCursor, 'hasMore' => $nextCursor !== null],
        'meta' => ['uid' => 'u1', 'endpoint' => '/test', 'generatedAt' => '2026-06-30T00:00:00Z'],
    ];
}

it('attaches the bearer key and returns the decoded envelope', function () {
    Saloon::fake(['rovi.test*/v1/me' => MockResponse::make(roviEnvelope(['name' => 'Taylor']))]);

    $profile = app(Rovi::class)->profile();

    expect($profile)->toBe(['name' => 'Taylor']);
    Saloon::assertSent(fn ($request, $response) => $request->hasHeader('Authorization', 'Bearer test-key')
        && str_contains($response->getPendingRequest()->getUrl(), '/v1/me'));
});

it('follows the cursor through every page and merges the data lists', function () {
    Saloon::fake([
        'rovi.test*/v1/me/foods*' => mockSequence([

            MockResponse::make(roviEnvelope([['id' => 'a'], ['id' => 'b']], nextCursor: 'page2')),

            MockResponse::make(roviEnvelope([['id' => 'c']])),

        ]), // hasMore=false ends it
    ]);

    $foods = app(Rovi::class)->foods();

    expect($foods)->toHaveCount(3)
        ->and(array_column($foods, 'id'))->toBe(['a', 'b', 'c']);

    // Second request carried the cursor from the first page's paging.
    Saloon::assertSent(fn ($request, $response) => str_contains($response->getPendingRequest()->getUrl(), 'cursor=page2'));
});

it('reads the daily food diary from the singular endpoint with date filters', function () {
    Saloon::fake([
        'rovi.test*/v1/me/food?*' => MockResponse::make(roviEnvelope([
            ['id' => '1', 'name' => 'Pain Au Chocolate', 'mealType' => 'Breakfast', 'calories' => 269],
        ])),
    ]);

    $diary = app(Rovi::class)->food(['from' => '2026-06-30', 'to' => '2026-06-30']);

    expect($diary)->toHaveCount(1)
        ->and($diary[0]['name'])->toBe('Pain Au Chocolate');

    Saloon::assertSent(fn ($request, $response) => str_contains($response->getPendingRequest()->getUrl(), '/v1/me/food?')
        && str_contains($response->getPendingRequest()->getUrl(), 'from=2026-06-30')
        && str_contains($response->getPendingRequest()->getUrl(), 'to=2026-06-30'));
});

it('reads the food library from the plural endpoint', function () {
    Saloon::fake(['rovi.test*/v1/me/foods*' => MockResponse::make(roviEnvelope([['id' => 'x', 'useCount' => 3]]))]);

    expect(app(Rovi::class)->foods())->toHaveCount(1);

    Saloon::assertSent(fn ($request, $response) => str_contains($response->getPendingRequest()->getUrl(), '/v1/me/foods'));
});

it('passes through date filters on daily logs', function () {
    Saloon::fake(['rovi.test*/v1/me/summaries*' => MockResponse::make(roviEnvelope([['id' => '2026-01-01']]))]);

    app(Rovi::class)->summaries(['from' => '2026-01-01', 'to' => '2026-06-30']);

    Saloon::assertSent(fn ($request, $response) => str_contains($response->getPendingRequest()->getUrl(), 'from=2026-01-01')
        && str_contains($response->getPendingRequest()->getUrl(), 'to=2026-06-30'));
});

it('returns null from object endpoints and [] from list endpoints on failure', function () {
    Saloon::fake(['rovi.test*' => MockResponse::make('nope', 500)]);

    expect(app(Rovi::class)->profile())->toBeNull()
        ->and(app(Rovi::class)->foods())->toBe([]);
});

it('returns null when no key is configured', function () {
    config(['services.rovi.key' => null]);
    Saloon::fake(['' => MockResponse::make('', 200)]);

    expect(app(Rovi::class)->get('/v1/me'))->toBeNull();
    Saloon::assertNothingSent();
});

it('converts a firestore timestamp to Carbon', function () {
    expect(Rovi::toCarbon(['_seconds' => 1569283200, '_nanoseconds' => 0]))
        ->toBeInstanceOf(CarbonImmutable::class)
        ->and(Rovi::toCarbon(['_seconds' => 1569283200])->toDateString())->toBe('2019-09-24')
        ->and(Rovi::toCarbon(null))->toBeNull()
        ->and(Rovi::toCarbon(['nope' => 1]))->toBeNull();
});
