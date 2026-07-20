<?php

use App\Services\LogoDev;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.logodev.token' => 'test-token']);
});

it('returns saved with body on an image response', function () {
    Http::fake(['*img.logo.dev*' => Http::response('PNG-BYTES', 200, ['Content-Type' => 'image/png'])]);

    $result = (new LogoDev)->logo('bp.com');

    expect($result['status'])->toBe('saved');
    expect($result['body'])->toBe('PNG-BYTES');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'img.logo.dev/bp.com')
            && $request['token'] === 'test-token'
            && $request['fallback'] === '404';
    });
});

it('returns unavailable on a 404 (no logo for the domain)', function () {
    Http::fake(['*img.logo.dev*' => Http::response('', 404)]);

    expect((new LogoDev)->logo('nope.example')['status'])->toBe('unavailable');
});

it('returns error on a server failure or non-image response', function () {
    Http::fake(['*img.logo.dev*' => Http::response('<html/>', 200, ['Content-Type' => 'text/html'])]);

    expect((new LogoDev)->logo('bp.com')['status'])->toBe('error');
});
