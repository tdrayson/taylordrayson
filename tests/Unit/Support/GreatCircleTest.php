<?php

use App\Support\GreatCircle;

it('keeps the endpoints exact, unchanged by interpolation', function () {
    $segments = GreatCircle::segments(50.077702, 19.7848, 51.148771, -0.192089);
    $lastSegment = end($segments);

    expect($segments[0][0])->toBe([50.077702, 19.7848])
        ->and(end($lastSegment))->toBe([51.148771, -0.192089]);
});

it('gives a long haul meaningfully more points than a short hop', function () {
    // Kraków to Gatwick, roughly 1,400km.
    $medium = GreatCircle::segments(50.077702, 19.7848, 51.148771, -0.192089);
    // Heathrow to Gatwick, roughly 40km.
    $short = GreatCircle::segments(51.4700, -0.4543, 51.1481, -0.1903);
    // London to Tokyo, roughly 9,500km.
    $long = GreatCircle::segments(51.5074, -0.1278, 35.6762, 139.6503);

    expect(count($short[0]))->toBeLessThan(count($medium[0]))
        ->and(count($medium[0]))->toBeLessThan(count($long[0]));
});

it('bows the midpoint off the straight line for a long route', function () {
    $originLat = 51.5074;
    $originLng = -0.1278;
    $destLat = 35.6762;
    $destLng = 139.6503;

    $segments = GreatCircle::segments($originLat, $originLng, $destLat, $destLng);
    $points = $segments[0];
    $middle = $points[intdiv(count($points), 2)];

    $naiveMidpoint = [($originLat + $destLat) / 2, ($originLng + $destLng) / 2];

    // London-Tokyo's great circle runs up near the Arctic; the naive lat/lng
    // midpoint sits far south of it, so the latitudes alone should diverge by
    // a wide margin rather than a rounding-sized one.
    expect(abs($middle[0] - $naiveMidpoint[0]))->toBeGreaterThan(10.0);
});

it('splits into segments that meet exactly at the antimeridian, not a sampled point short of it', function () {
    $segments = GreatCircle::segments(0.0, 170.0, 0.0, -170.0);

    expect($segments)->toHaveCount(2);

    foreach ($segments as $segment) {
        foreach ($segment as $point) {
            expect(abs($point[0]))->toBeLessThan(0.0001);
        }
    }

    $firstSegmentEnd = end($segments[0]);
    $secondSegmentStart = $segments[1][0];

    // Travelling from 170 to -170 the short way is eastward, so the first
    // segment must land exactly on +180 and the second resume exactly on -180.
    expect($firstSegmentEnd[1])->toBe(180.0)
        ->and($secondSegmentStart[1])->toBe(-180.0)
        ->and($firstSegmentEnd[0])->toBe($secondSegmentStart[0])
        ->and($segments[0][0])->toBe([0.0, 170.0])
        ->and(end($segments[1]))->toBe([0.0, -170.0]);
});

it('splits into segments that meet exactly at the antimeridian travelling west, not a sampled point short of it', function () {
    $segments = GreatCircle::segments(0.0, -170.0, 0.0, 170.0);

    expect($segments)->toHaveCount(2);

    foreach ($segments as $segment) {
        foreach ($segment as $point) {
            expect(abs($point[0]))->toBeLessThan(0.0001);
        }
    }

    $firstSegmentEnd = end($segments[0]);
    $secondSegmentStart = $segments[1][0];

    // Travelling from -170 to 170 the short way is westward, the mirror of
    // the eastward case above: the first segment lands exactly on -180 and
    // the second resumes exactly on +180.
    expect($firstSegmentEnd[1])->toBe(-180.0)
        ->and($secondSegmentStart[1])->toBe(180.0)
        ->and($firstSegmentEnd[0])->toBe($secondSegmentStart[0])
        ->and($segments[0][0])->toBe([0.0, -170.0])
        ->and(end($segments[1]))->toBe([0.0, 170.0]);
});

it('does not split a route that never crosses the antimeridian', function () {
    $segments = GreatCircle::segments(50.077702, 19.7848, 51.148771, -0.192089);

    expect($segments)->toHaveCount(1);
});

it('falls back to a plain two-point line for coincident or antipodal inputs, since no unique arc exists', function () {
    $coincident = GreatCircle::segments(51.5, -0.1, 51.5, -0.1);
    $antipodal = GreatCircle::segments(0.0, 0.0, 0.0, 180.0);

    expect($coincident)->toBe([[[51.5, -0.1], [51.5, -0.1]]])
        ->and($antipodal)->toBe([[[0.0, 0.0], [0.0, 180.0]]]);
});

it('also falls back for a pair that only just misses being exactly antipodal', function () {
    // 0.000001 degree short of the exact antipode (180.0): real coordinates
    // rounded to a handful of decimal places land here far more often than
    // on the exact antipode, and the guard has to catch this too.
    $nearAntipodal = GreatCircle::segments(0.0, 0.0, 0.0, 179.999999);

    expect($nearAntipodal)->toBe([[[0.0, 0.0], [0.0, 179.999999]]]);
});
