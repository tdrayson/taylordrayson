<?php

use App\Actions\LocatePhotoOnRoute;
use Carbon\CarbonImmutable;

/** A 5-point stream one second apart, starting at the given UTC time. */
function lpor_routeStart(): CarbonImmutable
{
    return CarbonImmutable::parse('2023-10-31T21:00:00Z');
}

function lpor_timeStream(): array
{
    return [0, 10, 20, 30, 40];
}

function lpor_latlngStream(): array
{
    return [[51.0, -0.0], [51.1, -0.1], [51.2, -0.2], [51.3, -0.3], [51.4, -0.4]];
}

function lpor_locate(string $capturedAt, ?array $time = null, ?array $latlng = null): ?array
{
    return (new LocatePhotoOnRoute)(
        CarbonImmutable::parse($capturedAt),
        lpor_routeStart(),
        $time ?? lpor_timeStream(),
        $latlng ?? lpor_latlngStream(),
    );
}

it('returns the exact point when the photo matches a sample', function () {
    expect(lpor_locate('2023-10-31T21:00:20Z'))->toBe([51.2, -0.2]);
});

it('returns lat then lng, not lng then lat', function () {
    [$lat, $lng] = lpor_locate('2023-10-31T21:00:20Z');

    expect($lat)->toBe(51.2)  // northern hemisphere latitude
        ->and($lng)->toBe(-0.2); // near-Greenwich longitude
});

it('returns the nearest sample when the photo falls between two', function () {
    // 22s is closer to the 20s sample than the 30s one.
    expect(lpor_locate('2023-10-31T21:00:22Z'))->toBe([51.2, -0.2]);

    // 28s is closer to the 30s sample.
    expect(lpor_locate('2023-10-31T21:00:28Z'))->toBe([51.3, -0.3]);
});

it('returns the earlier sample for an exact midpoint', function () {
    // 25s sits exactly between the 20s and 30s samples; earlier wins.
    expect(lpor_locate('2023-10-31T21:00:25Z'))->toBe([51.2, -0.2]);
});

it('returns the first and last samples at the stream bounds', function () {
    expect(lpor_locate('2023-10-31T21:00:00Z'))->toBe([51.0, -0.0])
        ->and(lpor_locate('2023-10-31T21:00:40Z'))->toBe([51.4, -0.4]);
});

it('returns null for a photo taken before the activity started', function () {
    expect(lpor_locate('2023-10-31T20:59:59Z'))->toBeNull();
});

it('returns null for a photo taken after the activity ended', function () {
    expect(lpor_locate('2023-10-31T21:00:41Z'))->toBeNull();
});

it('returns null for an empty stream', function () {
    expect(lpor_locate('2023-10-31T21:00:20Z', [], []))->toBeNull();
});

it('handles a single-point stream without breaking the search', function () {
    expect(lpor_locate('2023-10-31T21:00:00Z', [0], [[51.5, -0.5]]))->toBe([51.5, -0.5])
        ->and(lpor_locate('2023-10-31T21:00:05Z', [0], [[51.5, -0.5]]))->toBeNull();
});

it('returns null when the latlng stream is shorter than the time stream', function () {
    expect(lpor_locate('2023-10-31T21:00:40Z', [0, 10, 20, 30, 40], [[51.0, -0.0]]))->toBeNull();
});
