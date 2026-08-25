<?php

namespace App\Support;

/**
 * The IANA timezone at a coordinate, from a bundled table of cities.
 *
 * The zone of the nearest city, which is a heuristic: a point near a timezone
 * border with no city close by can land on the wrong side. Checked against
 * polygon boundary data for every coordinate this site actually holds and it
 * agreed on all of them, including Lanzarote against mainland Spain and both
 * airports that a trip's zone gets wrong.
 *
 * Bundled rather than fetched so a lookup cannot fail: the API this replaced
 * went offline and took every unresolved check-in with it.
 */
class CityTimezones
{
    /**
     * How far either side of the point to consider, in degrees. Rejecting on
     * raw degrees first is what keeps this cheap: distance maths runs on the
     * handful of rows that survive rather than on all 34,000.
     */
    private const SPAN = 3.0;

    /** Widened once when nothing is near, for somewhere genuinely remote. */
    private const WIDE_SPAN = 12.0;

    public function forCoordinate(float $latitude, float $longitude): ?string
    {
        return $this->nearest($latitude, $longitude, self::SPAN)
            ?? $this->nearest($latitude, $longitude, self::WIDE_SPAN);
    }

    /** The zone of the closest city within a bounding box, or null if none is. */
    private function nearest(float $latitude, float $longitude, float $span): ?string
    {
        $handle = @fopen($this->path(), 'r');

        if ($handle === false) {
            return null;
        }

        $zone = null;
        $closest = INF;

        while (($line = fgets($handle)) !== false) {
            $city = explode(',', $line, 3);

            if (count($city) < 3) {
                continue;
            }

            $northing = (float) $city[0] - $latitude;

            if ($northing > $span || $northing < -$span) {
                continue;
            }

            $easting = (float) $city[1] - $longitude;

            if ($easting > $span || $easting < -$span) {
                continue;
            }

            // Squared degrees: enough to rank candidates, and no trigonometry.
            $distance = ($northing * $northing) + ($easting * $easting);

            if ($distance < $closest) {
                $closest = $distance;
                $zone = trim($city[2]);
            }
        }

        fclose($handle);

        return $zone;
    }

    public function path(): string
    {
        return database_path('data/city-timezones.csv');
    }
}
