<?php

use App\Actions\LocatePhotoOnRoute;
use App\Actions\ResolvePhotoCoordinate;
use Carbon\CarbonImmutable;

function resolvePhotoCoordinate(): ResolvePhotoCoordinate
{
    return new ResolvePhotoCoordinate(new LocatePhotoOnRoute);
}

/** A 3-point stream 10s apart from a UTC start. */
function rpcStreams(): array
{
    return [[0, 10, 20], [[51.0, -0.0], [51.1, -0.1], [51.2, -0.2]]];
}

it('returns the photo location when Strava provides one', function () {
    $coordinate = resolvePhotoCoordinate()(['location' => [48.86, 2.35], 'created_at' => '2023-10-31T21:00:10Z']);

    expect($coordinate)->toBe([48.86, 2.35]);
});

it('prefers the location over the stream', function () {
    [$time, $latlng] = rpcStreams();

    $coordinate = resolvePhotoCoordinate()(
        ['location' => [48.86, 2.35], 'created_at' => '2023-10-31T21:00:10Z'],
        CarbonImmutable::parse('2023-10-31T21:00:00Z'),
        $time,
        $latlng,
    );

    expect($coordinate)->toBe([48.86, 2.35]);
});

it('falls back to the stream when there is no location', function () {
    [$time, $latlng] = rpcStreams();

    $coordinate = resolvePhotoCoordinate()(
        ['created_at' => '2023-10-31T21:00:10Z'],
        CarbonImmutable::parse('2023-10-31T21:00:00Z'),
        $time,
        $latlng,
    );

    expect($coordinate)->toBe([51.1, -0.1]);
});

it('treats [0, 0] as no location and falls back to the stream', function () {
    [$time, $latlng] = rpcStreams();

    $coordinate = resolvePhotoCoordinate()(
        ['location' => [0, 0], 'created_at' => '2023-10-31T21:00:10Z'],
        CarbonImmutable::parse('2023-10-31T21:00:00Z'),
        $time,
        $latlng,
    );

    expect($coordinate)->toBe([51.1, -0.1]);
});

it('returns null when there is neither a location nor a start for the stream', function () {
    [$time, $latlng] = rpcStreams();

    $coordinate = resolvePhotoCoordinate()(['created_at' => '2023-10-31T21:00:10Z'], null, $time, $latlng);

    expect($coordinate)->toBeNull();
});

it('returns null for a malformed created_at with no location', function () {
    [$time, $latlng] = rpcStreams();

    $coordinate = resolvePhotoCoordinate()(
        ['created_at' => 'not-a-date'],
        CarbonImmutable::parse('2023-10-31T21:00:00Z'),
        $time,
        $latlng,
    );

    expect($coordinate)->toBeNull();
});

it('exposes locationFor so callers can skip an unnecessary stream fetch', function () {
    $resolve = resolvePhotoCoordinate();

    expect($resolve->locationFor(['location' => [48.86, 2.35]]))->toBe([48.86, 2.35])
        ->and($resolve->locationFor(['location' => [0, 0]]))->toBeNull()
        ->and($resolve->locationFor([]))->toBeNull();
});
