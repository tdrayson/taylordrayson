<?php

use App\Services\LogoStream;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.logostream.key' => 'test-key']);
});

it('returns saved with bytes for a real airline logo', function () {
    Http::fake([
        '*/iata/BA*' => Http::response('PNG-BYTES', 200, ['Content-Type' => 'image/png', 'x-asset' => 'BA_logo']),
    ]);

    expect(app(LogoStream::class)->airlineLogo('BA', 'logo-transparent'))
        ->toBe(['status' => 'saved', 'body' => 'PNG-BYTES']);
});

it('reports a placeholder asset as unavailable', function () {
    Http::fake([
        '*/iata/ZZ*' => Http::response('<svg/>', 200, ['Content-Type' => 'image/svg+xml', 'x-asset' => '-']),
    ]);

    expect(app(LogoStream::class)->airlineLogo('ZZ', 'logo-transparent')['status'])->toBe('unavailable');
});

it('reports a failed request as an error', function () {
    Http::fake(['*/iata/XX*' => Http::response('boom', 500)]);

    expect(app(LogoStream::class)->airlineLogo('XX', 'logo-transparent'))
        ->toBe(['status' => 'error', 'body' => null]);
});

it('maps an aviation route into duration, timezones and miles', function () {
    Http::fake([
        '*aviation-api*' => Http::response(['data' => [[
            'duration_min' => 90,
            'departure_timezone' => 'Europe/London',
            'arrival_timezone' => 'Europe/Berlin',
            'distance_km' => 1000,
        ]]]),
    ]);

    expect(app(LogoStream::class)->route('LHR', 'BER'))->toBe([
        'duration' => 5400,
        'departure_timezone' => 'Europe/London',
        'arrival_timezone' => 'Europe/Berlin',
        'distance_miles' => 621,
    ]);
});

it('returns null when the aviation api has no route', function () {
    Http::fake(['*aviation-api*' => Http::response(['data' => []])]);

    expect(app(LogoStream::class)->route('AAA', 'BBB'))->toBeNull();
});
