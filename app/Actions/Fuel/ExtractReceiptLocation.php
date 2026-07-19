<?php

namespace App\Actions\Fuel;

use Carbon\CarbonImmutable;

/**
 * Reads GPS coordinates and the capture timestamp from a photo's EXIF data.
 */
class ExtractReceiptLocation
{
    /**
     * Returns the receipt's location, or null when GPS or a timestamp is absent.
     */
    public function __invoke(string $path): ?ReceiptLocation
    {
        $exif = @exif_read_data($path);

        if ($exif === false) {
            return null;
        }

        $latitude = self::coordinate($exif['GPSLatitude'] ?? null, $exif['GPSLatitudeRef'] ?? null);
        $longitude = self::coordinate($exif['GPSLongitude'] ?? null, $exif['GPSLongitudeRef'] ?? null);
        $timestamp = $exif['DateTimeOriginal'] ?? $exif['DateTime'] ?? null;

        if ($latitude === null || $longitude === null || $timestamp === null) {
            return null;
        }

        return new ReceiptLocation(
            path: $path,
            capturedAt: CarbonImmutable::createFromFormat('Y:m:d H:i:s', $timestamp),
            latitude: $latitude,
            longitude: $longitude,
        );
    }

    /**
     * Convert EXIF degrees/minutes/seconds rationals to a signed decimal degree.
     *
     * @param  array<int, string>|null  $parts
     */
    public static function coordinate(?array $parts, ?string $ref): ?float
    {
        if ($parts === null || count($parts) < 3) {
            return null;
        }

        $decimal = self::rational($parts[0])
            + self::rational($parts[1]) / 60
            + self::rational($parts[2]) / 3600;

        return in_array($ref, ['S', 'W'], true) ? -$decimal : $decimal;
    }

    /**
     * Evaluate a single "numerator/denominator" EXIF rational as a float.
     */
    private static function rational(string $value): float
    {
        [$numerator, $denominator] = array_pad(explode('/', $value), 2, '1');

        return (float) $denominator === 0.0 ? 0.0 : (float) $numerator / (float) $denominator;
    }
}
