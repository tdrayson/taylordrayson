<?php

namespace App\Support;

/**
 * Spherical interpolation between two coordinates, for drawing the curved
 * path an aircraft (or anything else) actually travels rather than a
 * straight line on a flat projection.
 */
final class GreatCircle
{
    private const EARTH_RADIUS_KM = 6371.0;

    /** Roughly one vertex per 100km of great-circle distance. */
    private const KM_PER_POINT = 100.0;

    private const MIN_POINTS = 8;

    private const MAX_POINTS = 128;

    /** Radians (~6mm at Earth's surface); guards both coincident and antipodal inputs, where sin(delta) collapses toward zero. */
    private const DEGENERATE_EPSILON = 1e-9;

    /**
     * Interpolated points between two coordinates, split into segments
     * wherever the great circle crosses the antimeridian.
     *
     * @return list<list<array{0: float, 1: float}>> Segments of [lat, lng] points.
     */
    public static function segments(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): array
    {
        $lat1 = deg2rad($latitudeA);
        $lng1 = deg2rad($longitudeA);
        $lat2 = deg2rad($latitudeB);
        $lng2 = deg2rad($longitudeB);

        $delta = 2 * asin(sqrt(
            sin(($lat2 - $lat1) / 2) ** 2
            + cos($lat1) * cos($lat2) * sin(($lng2 - $lng1) / 2) ** 2
        ));

        // Coincident points have no arc to draw; antipodal points have infinitely
        // many, since every great circle through one passes through the other.
        // Either way sin(delta) is ~0 here, so fall back to the plain chord.
        if ($delta < self::DEGENERATE_EPSILON || abs(M_PI - $delta) < self::DEGENERATE_EPSILON) {
            return [[[$latitudeA, $longitudeA], [$latitudeB, $longitudeB]]];
        }

        $pointCount = (int) min(self::MAX_POINTS, max(self::MIN_POINTS, round(self::EARTH_RADIUS_KM * $delta / self::KM_PER_POINT)));

        $points = [];
        $fractions = [];

        for ($index = 0; $index < $pointCount; $index++) {
            $fraction = $index / ($pointCount - 1);
            $fractions[] = $fraction;
            $points[] = self::pointAt($fraction, $lat1, $lng1, $lat2, $lng2, $delta);
        }

        // Trig round-trips don't reproduce the input bit-for-bit; pin the
        // endpoints back to the exact airport coordinates.
        $points[0] = [$latitudeA, $longitudeA];
        $points[$pointCount - 1] = [$latitudeB, $longitudeB];

        return self::splitAtAntimeridian($points, $fractions, $lat1, $lng1, $lat2, $lng2, $delta);
    }

    /**
     * @return array{0: float, 1: float} A [lat, lng] point at $fraction (0-1) along the great circle.
     */
    private static function pointAt(float $fraction, float $lat1, float $lng1, float $lat2, float $lng2, float $delta): array
    {
        [$x, $y, $z] = self::slerp($fraction, $lat1, $lng1, $lat2, $lng2, $delta);

        return [
            rad2deg(atan2($z, sqrt($x * $x + $y * $y))),
            rad2deg(atan2($y, $x)),
        ];
    }

    /**
     * @return array{0: float, 1: float, 2: float} Cartesian [x, y, z] on the unit sphere at $fraction.
     */
    private static function slerp(float $fraction, float $lat1, float $lng1, float $lat2, float $lng2, float $delta): array
    {
        $scaleFrom = sin((1 - $fraction) * $delta) / sin($delta);
        $scaleTo = sin($fraction * $delta) / sin($delta);

        return [
            $scaleFrom * cos($lat1) * cos($lng1) + $scaleTo * cos($lat2) * cos($lng2),
            $scaleFrom * cos($lat1) * sin($lng1) + $scaleTo * cos($lat2) * sin($lng2),
            $scaleFrom * sin($lat1) + $scaleTo * sin($lat2),
        ];
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     * @param  list<float>  $fractions  $fractions[$i] is where $points[$i] sits along the great circle (0-1).
     * @return list<list<array{0: float, 1: float}>>
     */
    private static function splitAtAntimeridian(array $points, array $fractions, float $lat1, float $lng1, float $lat2, float $lng2, float $delta): array
    {
        $segments = [];
        $current = [$points[0]];

        for ($index = 1, $count = count($points); $index < $count; $index++) {
            $previousLongitude = $points[$index - 1][1];
            $longitude = $points[$index][1];

            if (abs($longitude - $previousLongitude) > 180) {
                // Wrapping from ~180 to ~-180 means the path is heading east; the reverse means west.
                $eastward = $longitude < $previousLongitude;
                $crossingLatitude = self::crossingLatitude($fractions[$index - 1], $fractions[$index], $lat1, $lng1, $lat2, $lng2, $delta);

                $current[] = [$crossingLatitude, $eastward ? 180.0 : -180.0];
                $segments[] = $current;
                $current = [[$crossingLatitude, $eastward ? -180.0 : 180.0]];
            }

            $current[] = $points[$index];
        }

        $segments[] = $current;

        return $segments;
    }

    /**
     * The exact latitude where the great circle crosses longitude ±180,
     * found by bisecting the two samples straddling it. The crossing is where
     * the slerp's y-component (east/west component in Cartesian space) is
     * zero, since near ±180 that component alone changes sign.
     */
    private static function crossingLatitude(float $fractionA, float $fractionB, float $lat1, float $lng1, float $lat2, float $lng2, float $delta): float
    {
        $ySignAt = function (float $fraction) use ($lat1, $lng1, $lat2, $lng2, $delta): float {
            return self::slerp($fraction, $lat1, $lng1, $lat2, $lng2, $delta)[1];
        };

        $signAtA = $ySignAt($fractionA);

        for ($i = 0; $i < 40; $i++) {
            $midpoint = ($fractionA + $fractionB) / 2;
            $signAtMidpoint = $ySignAt($midpoint);

            if (($signAtMidpoint <=> 0.0) === ($signAtA <=> 0.0)) {
                $fractionA = $midpoint;
                $signAtA = $signAtMidpoint;
            } else {
                $fractionB = $midpoint;
            }
        }

        return self::pointAt(($fractionA + $fractionB) / 2, $lat1, $lng1, $lat2, $lng2, $delta)[0];
    }
}
