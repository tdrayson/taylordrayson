<?php

namespace App\Support;

/**
 * Builds Mapbox Static Images API URLs for entry route maps. This is the
 * server-side twin of resources/js/lib/staticMap.js (which renders the live
 * timeline thumbnails); the two must stay visually in step.
 *
 * INTERIM: renders live from the Mapbox API (token from config/services.php).
 * The plan is to generate each image once and host it on Cloudflare R2, then
 * point callers at the stored URL instead of building a Mapbox URL on the fly.
 */
class StaticMap
{
    private const STYLE = 'mapbox/light-v11';

    /** Web Mercator tile size, the basis for the zoom -> pixel scale. */
    private const TILE = 512;

    /** Dash and gap lengths, in pixels, for the flight arc's dashed line. */
    private const DASH_LENGTH_PX = 13;

    private const GAP_LENGTH_PX = 11;

    /**
     * Static map for an encoded polyline route (e.g. an activity GPS trace),
     * using Mapbox's own auto-fit since there are no fixed-size markers to keep
     * stable.
     */
    public static function route(?string $polyline, string $color = '2e9e6a', int $width = 1200, int $height = 630, int $padding = 64, string $style = self::STYLE): ?string
    {
        if (! $polyline) {
            return null;
        }

        $overlay = self::pathOverlay($polyline, 5, $color, '0.85');

        return sprintf(
            'https://api.mapbox.com/styles/v1/%s/static/%s/auto/%dx%d@2x?padding=%d&attribution=false&logo=false&access_token=%s',
            $style, $overlay, $width, $height, $padding, config('services.mapbox.token')
        );
    }

    /**
     * A single check-in marker (white-ringed pin) centred on a coordinate.
     */
    public static function marker(float $lng, float $lat, string $color, int $zoom = 14, int $width = 1200, int $height = 630): string
    {
        return sprintf(
            'https://api.mapbox.com/styles/v1/%s/static/pin-l+%s(%s,%s)/%s,%s,%d/%dx%d@2x?attribution=false&logo=false&access_token=%s',
            self::STYLE, $color, $lng, $lat, $lng, $lat, $zoom, $width, $height, config('services.mapbox.token')
        );
    }

    /**
     * Static map for a flight: a dashed great-circle arc with a direction
     * arrowhead and white airport markers. The zoom is computed here (rather than
     * left to Mapbox's `auto`) so dashes, markers, and the arrowhead are sized in
     * fixed pixels and stay identical across every route, short hop or long haul.
     *
     * @return string|null The image URL, or null when any coordinate is missing/invalid.
     */
    public static function arc(?float $originLng, ?float $originLat, ?float $destLng, ?float $destLat, string $color = '209fdf', int $width = 1200, int $height = 630, int $padding = 60): ?string
    {
        foreach ([$originLng, $originLat, $destLng, $destLat] as $coordinate) {
            if ($coordinate === null || ! is_finite($coordinate)) {
                return null;
            }
        }

        $world = array_map(
            fn (array $point): array => self::lngLatToWorld($point[0], $point[1]),
            self::greatCircle($originLat, $originLng, $destLat, $destLng, 128)
        );

        $xValues = array_column($world, 0);
        $yValues = array_column($world, 1);
        $minimumX = min($xValues);
        $maximumX = max($xValues);
        $minimumY = min($yValues);
        $maximumY = max($yValues);

        // Fit the arc's bounding box into the padded frame, then derive the
        // world-units-per-pixel scale at that zoom.
        $horizontalSpan = max($maximumX - $minimumX, 1e-9);
        $verticalSpan = max($maximumY - $minimumY, 1e-9);
        $usableWidth = max($width - 2 * $padding, 1);
        $usableHeight = max($height - 2 * $padding, 1);
        $zoom = max(0, min(
            log($usableWidth / ($horizontalSpan * self::TILE), 2),
            log($usableHeight / ($verticalSpan * self::TILE), 2),
            16,
        ));
        $scale = self::TILE * (2 ** $zoom);
        [$centerLongitude, $centerLatitude] = self::worldToLngLat(($minimumX + $maximumX) / 2, ($minimumY + $maximumY) / 2);

        // Cumulative pixel length along the arc, so dashes land at fixed intervals.
        $lengths = [0.0];
        $count = count($world);
        for ($index = 1; $index < $count; $index++) {
            $pixelDeltaX = ($world[$index][0] - $world[$index - 1][0]) * $scale;
            $pixelDeltaY = ($world[$index][1] - $world[$index - 1][1]) * $scale;
            $lengths[$index] = $lengths[$index - 1] + hypot($pixelDeltaX, $pixelDeltaY);
        }
        $total = $lengths[$count - 1];

        // World coordinate at a given pixel distance along the arc.
        $at = function (float $distance) use ($world, $lengths, $count): array {
            for ($index = 1; $index < $count; $index++) {
                if ($lengths[$index] >= $distance) {
                    $segment = ($lengths[$index] - $lengths[$index - 1]) ?: 1;
                    $fraction = ($distance - $lengths[$index - 1]) / $segment;

                    return [
                        $world[$index - 1][0] + ($world[$index][0] - $world[$index - 1][0]) * $fraction,
                        $world[$index - 1][1] + ($world[$index][1] - $world[$index - 1][1]) * $fraction,
                    ];
                }
            }

            return $world[$count - 1];
        };

        $overlays = [];

        for ($distance = 0; $distance < $total; $distance += self::DASH_LENGTH_PX + self::GAP_LENGTH_PX) {
            $dash = self::worldDash($at($distance), $at(min($distance + self::DASH_LENGTH_PX, $total)));
            $overlays[] = "path-5+{$color}-1(".rawurlencode($dash).')';
        }

        // Direction-of-travel arrowhead at the midpoint, sized in fixed pixels.
        $mid = $at($total / 2);
        $ahead = $at(min($total / 2 + 1, $total));
        $directionX = $ahead[0] - $mid[0];
        $directionY = $ahead[1] - $mid[1];
        $length = hypot($directionX, $directionY) ?: 1;
        $directionX /= $length;
        $directionY /= $length;
        $perpendicularX = -$directionY;
        $perpendicularY = $directionX;
        $tip = 16 / $scale;
        $back = 5 / $scale;
        $half = 10 / $scale;
        $tipPoint = self::worldToLngLat($mid[0] + $directionX * $tip, $mid[1] + $directionY * $tip);
        $leftPoint = self::worldToLngLat($mid[0] - $directionX * $back + $perpendicularX * $half, $mid[1] - $directionY * $back + $perpendicularY * $half);
        $rightPoint = self::worldToLngLat($mid[0] - $directionX * $back - $perpendicularX * $half, $mid[1] - $directionY * $back - $perpendicularY * $half);
        $arrow = self::encodePolyline([
            [$tipPoint[1], $tipPoint[0]],
            [$leftPoint[1], $leftPoint[0]],
            [$rightPoint[1], $rightPoint[0]],
            [$tipPoint[1], $tipPoint[0]],
        ]);
        $overlays[] = "path-1+{$color}-1+{$color}-1(".rawurlencode($arrow).')';

        // Airport markers (white fill, coloured ring) at a fixed pixel radius, on top.
        $markerRadius = 9 / $scale;
        $overlays[] = "path-6+{$color}-1+ffffff-1(".rawurlencode(self::worldCircle($world[0], $markerRadius)).')';
        $overlays[] = "path-6+{$color}-1+ffffff-1(".rawurlencode(self::worldCircle($world[$count - 1], $markerRadius)).')';

        return sprintf(
            'https://api.mapbox.com/styles/v1/%s/static/%s/%s,%s,%s/%dx%d@2x?attribution=false&logo=false&access_token=%s',
            self::STYLE,
            implode(',', $overlays),
            number_format($centerLongitude, 5, '.', ''),
            number_format($centerLatitude, 5, '.', ''),
            number_format($zoom, 2, '.', ''),
            $width,
            $height,
            config('services.mapbox.token')
        );
    }

    /**
     * A `path` overlay for an encoded polyline. The polyline charset is all >= '?'
     * (63), so the overlay's own delimiters (parens, commas) never collide.
     */
    private static function pathOverlay(string $polyline, int $width, string $color, string $opacity): string
    {
        return "path-{$width}+{$color}-{$opacity}(".rawurlencode($polyline).')';
    }

    /**
     * Project [lng, lat] to Web Mercator world coordinates in the unit square.
     *
     * @return array{float, float}
     */
    private static function lngLatToWorld(float $lng, float $lat): array
    {
        $sinLat = sin($lat * M_PI / 180);

        return [($lng + 180) / 360, 0.5 - log((1 + $sinLat) / (1 - $sinLat)) / (4 * M_PI)];
    }

    /**
     * Inverse of lngLatToWorld: world coordinates back to [lng, lat].
     *
     * @return array{float, float}
     */
    private static function worldToLngLat(float $x, float $y): array
    {
        return [$x * 360 - 180, atan(sinh(M_PI * (1 - 2 * $y))) * 180 / M_PI];
    }

    /**
     * A closed circle of a given world-unit radius around a world point, encoded
     * as a [lat, lng] polyline. Mercator is conformal, so a world circle renders
     * as a true on-screen circle.
     *
     * @param  array{float, float}  $center
     */
    private static function worldCircle(array $center, float $radius, int $segments = 28): string
    {
        [$centerX, $centerY] = $center;
        $points = [];

        for ($index = 0; $index <= $segments; $index++) {
            $theta = 2 * M_PI * $index / $segments;
            [$lng, $lat] = self::worldToLngLat($centerX + $radius * cos($theta), $centerY + $radius * sin($theta));
            $points[] = [$lat, $lng];
        }

        return self::encodePolyline($points);
    }

    /**
     * A 2-point dash between two world points, encoded as a [lat, lng] polyline.
     *
     * @param  array{float, float}  $from
     * @param  array{float, float}  $to
     */
    private static function worldDash(array $from, array $to): string
    {
        $start = self::worldToLngLat($from[0], $from[1]);
        $end = self::worldToLngLat($to[0], $to[1]);

        return self::encodePolyline([[$start[1], $start[0]], [$end[1], $end[0]]]);
    }

    /**
     * Interpolate a great-circle arc between two coordinates.
     *
     * @return array<int, array{float, float}> Points as [lng, lat].
     */
    private static function greatCircle(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB, int $segments = 128): array
    {
        $lat1 = deg2rad($latitudeA);
        $lng1 = deg2rad($longitudeA);
        $lat2 = deg2rad($latitudeB);
        $lng2 = deg2rad($longitudeB);

        $delta = 2 * asin(sqrt(
            sin(($lat2 - $lat1) / 2) ** 2
            + cos($lat1) * cos($lat2) * sin(($lng2 - $lng1) / 2) ** 2,
        ));

        if ($delta === 0.0) {
            return [[$longitudeA, $latitudeA], [$longitudeB, $latitudeB]];
        }

        $points = [];
        $previousLongitude = null;

        for ($index = 0; $index <= $segments; $index++) {
            $fraction = $index / $segments;
            $scaleFrom = sin((1 - $fraction) * $delta) / sin($delta);
            $scaleTo = sin($fraction * $delta) / sin($delta);

            $x = $scaleFrom * cos($lat1) * cos($lng1) + $scaleTo * cos($lat2) * cos($lng2);
            $y = $scaleFrom * cos($lat1) * sin($lng1) + $scaleTo * cos($lat2) * sin($lng2);
            $z = $scaleFrom * sin($lat1) + $scaleTo * sin($lat2);

            $latitude = rad2deg(atan2($z, sqrt($x * $x + $y * $y)));
            $longitude = rad2deg(atan2($y, $x));

            if ($previousLongitude !== null) {
                while ($longitude - $previousLongitude > 180) {
                    $longitude -= 360;
                }
                while ($longitude - $previousLongitude < -180) {
                    $longitude += 360;
                }
            }

            $previousLongitude = $longitude;
            $points[] = [$longitude, $latitude];
        }

        return $points;
    }

    /**
     * Google-encode a list of [lat, lng] points (precision 1e5). Mirrors
     * encodePolyline in resources/js/lib/geo.js.
     *
     * @param  array<int, array{float, float}>  $points
     */
    private static function encodePolyline(array $points): string
    {
        $encodeValue = function (int $value): string {
            $shifted = $value < 0 ? ~($value << 1) : ($value << 1);
            $chunk = '';

            while ($shifted >= 0x20) {
                $chunk .= chr((0x20 | ($shifted & 0x1F)) + 63);
                $shifted >>= 5;
            }

            return $chunk.chr($shifted + 63);
        };

        $previousLatitude = 0;
        $previousLongitude = 0;
        $result = '';

        foreach ($points as [$latitude, $longitude]) {
            $latitudeScaled = (int) round($latitude * 1e5);
            $longitudeScaled = (int) round($longitude * 1e5);
            $result .= $encodeValue($latitudeScaled - $previousLatitude).$encodeValue($longitudeScaled - $previousLongitude);
            $previousLatitude = $latitudeScaled;
            $previousLongitude = $longitudeScaled;
        }

        return $result;
    }
}
