<?php

use App\Services\LogoDev;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function () {
    config(['services.logodev.token' => 'test-token']);
});

it('returns saved with body on an image response', function () {
    Saloon::fake(['img.logo.dev*' => MockResponse::make('PNG-BYTES', 200, ['Content-Type' => 'image/png'])]);

    $result = app(LogoDev::class)->logo('bp.com');

    expect($result['status'])->toBe('saved');
    expect($result['body'])->toBe('PNG-BYTES');

    Saloon::assertSent(function ($request, $response) {
        $url = $response->getPendingRequest()->getUrl();
        $query = $request->query()->all();

        return str_contains($url, 'img.logo.dev/bp.com')
            && $query['token'] === 'test-token'
            && $query['fallback'] === '404';
    });
});

it('returns unavailable on a 404 (no logo for the domain)', function () {
    Saloon::fake(['img.logo.dev*' => MockResponse::make('', 404)]);

    expect(app(LogoDev::class)->logo('nope.example')['status'])->toBe('unavailable');
});

it('returns error on a server failure or non-image response', function () {
    Saloon::fake(['img.logo.dev*' => MockResponse::make('<html/>', 200, ['Content-Type' => 'text/html'])]);

    expect(app(LogoDev::class)->logo('bp.com')['status'])->toBe('error');
});
