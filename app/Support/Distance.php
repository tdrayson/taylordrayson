<?php

namespace App\Support;

class Distance
{
    public const METRES_PER_MILE = 1609.344;

    /**
     * Metres to kilometres for display, one decimal place.
     */
    public static function km(?int $metres): ?float
    {
        return $metres === null ? null : round($metres / 1000, 1);
    }

    /**
     * Metres to miles for display. Whole miles by default; pass a precision
     * for short distances where tenths matter (e.g. a 5k run is 3.1 mi).
     */
    public static function miles(?int $metres, int $precision = 0): float|int|null
    {
        if ($metres === null) {
            return null;
        }

        $miles = round($metres / self::METRES_PER_MILE, $precision);

        return $precision === 0 ? (int) $miles : $miles;
    }

    /**
     * Miles to integer metres for storage.
     */
    public static function fromMiles(float|int $miles): int
    {
        return (int) round($miles * self::METRES_PER_MILE);
    }

    /**
     * Kilometres to integer metres for storage.
     */
    public static function fromKm(float|int $km): int
    {
        return (int) round($km * 1000);
    }
}
