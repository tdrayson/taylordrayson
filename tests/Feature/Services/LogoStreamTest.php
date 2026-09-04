<?php

use App\Services\LogoStream;
use App\Support\Distance;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function () {
    config(['services.logostream.key' => 'test-key']);
});

it('returns saved with bytes for a real airline logo', function () {
    Saloon::fake([
        '/iata/BA*' => MockResponse::make('PNG-BYTES', 200, ['Content-Type' => 'image/png', 'x-asset' => 'BA_logo']),
    ]);

    expect(app(LogoStream::class)->airlineLogo('BA', 'logo-transparent'))
        ->toBe(['status' => 'saved', 'body' => 'PNG-BYTES']);
});

it('reports a placeholder asset as unavailable', function () {
    Saloon::fake([
        '/iata/ZZ*' => MockResponse::make('<svg/>', 200, ['Content-Type' => 'image/svg+xml', 'x-asset' => '-']),
    ]);

    expect(app(LogoStream::class)->airlineLogo('ZZ', 'logo-transparent')['status'])->toBe('unavailable');
});

it('reports a failed request as an error', function () {
    Saloon::fake(['/iata/XX*' => MockResponse::make('boom', 500)]);

    expect(app(LogoStream::class)->airlineLogo('XX', 'logo-transparent'))
        ->toBe(['status' => 'error', 'body' => null]);
});

it('maps an aviation route into duration, timezones and miles', function () {
    Saloon::fake([
        'aviation-api*' => MockResponse::make(['data' => [[
            'duration_min' => 90,
            'departure_timezone' => 'Europe/London',
            'arrival_timezone' => 'Europe/Berlin',
            'distance_km' => 1000,
        ]]]),
    ]);

    $result = app(LogoStream::class)->route('LHR', 'BER');

    expect($result['duration'])->toBe(5400)
        ->and($result['departure_timezone'])->toBe('Europe/London')
        ->and($result['arrival_timezone'])->toBe('Europe/Berlin')
        ->and(Distance::miles($result['distance']))->toBe(621);
});

it('returns null when the aviation api has no route', function () {
    Saloon::fake(['aviation-api*' => MockResponse::make(['data' => []])]);

    expect(app(LogoStream::class)->route('AAA', 'BBB'))->toBeNull();
});
