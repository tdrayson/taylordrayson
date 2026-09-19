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

    /**
     * Interpolated points from one coordinate to the other, split into
     * separate segments wherever the path crosses the antimeridian. GeoJSON's
     * own guidance is to split there: a single LineString crossing it would
     * have its longitude jump from ~180 to ~-180, which most tools render as
     * a line wrapping the wrong way round the entire globe.
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

        if ($delta === 0.0) {
            return [[[$latitudeA, $longitudeA], [$latitudeB, $longitudeB]]];
        }

        $pointCount = (int) min(self::MAX_POINTS, max(self::MIN_POINTS, round(self::EARTH_RADIUS_KM * $delta / self::KM_PER_POINT)));

        $points = [];

        for ($index = 0; $index < $pointCount; $index++) {
            $fraction = $index / ($pointCount - 1);
            $scaleFrom = sin((1 - $fraction) * $delta) / sin($delta);
            $scaleTo = sin($fraction * $delta) / sin($delta);

            $x = $scaleFrom * cos($lat1) * cos($lng1) + $scaleTo * cos($lat2) * cos($lng2);
            $y = $scaleFrom * cos($lat1) * sin($lng1) + $scaleTo * cos($lat2) * sin($lng2);
            $z = $scaleFrom * sin($lat1) + $scaleTo * sin($lat2);

            $points[] = [
                rad2deg(atan2($z, sqrt($x * $x + $y * $y))),
                rad2deg(atan2($y, $x)),
            ];
        }

        // Trig round-trips don't reproduce the input bit-for-bit; pin the
        // endpoints back to the exact airport coordinates.
        $points[0] = [$latitudeA, $longitudeA];
        $points[$pointCount - 1] = [$latitudeB, $longitudeB];

        return self::splitAtAntimeridian($points);
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     * @return list<list<array{0: float, 1: float}>>
     */
    private static function splitAtAntimeridian(array $points): array
    {
        $segments = [];
        $current = [$points[0]];

        for ($index = 1, $count = count($points); $index < $count; $index++) {
            if (abs($points[$index][1] - $points[$index - 1][1]) > 180) {
                $segments[] = $current;
                $current = [];
            }

            $current[] = $points[$index];
        }

        $segments[] = $current;

        return $segments;
    }
}
