<?php

use App\Services\TimeApi;
use Illuminate\Support\Facades\Http;

it('resolves an IANA timezone for a coordinate', function () {
    Http::fake(['*timeapi.io*' => Http::response(['timeZone' => 'Asia/Tokyo'])]);

    expect(app(TimeApi::class)->timezoneForCoordinate(35.6, 139.7))->toBe('Asia/Tokyo');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/timezone/coordinate')
        && $request['latitude'] == 35.6
        && $request['longitude'] == 139.7);
});

it('returns null when the request fails', function () {
    Http::fake(['*timeapi.io*' => Http::response('error', 500)]);

    expect(app(TimeApi::class)->timezoneForCoordinate(0, 0))->toBeNull();
});
