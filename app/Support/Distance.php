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
     * Metres to whole miles for display.
     */
    public static function miles(?int $metres): ?int
    {
        return $metres === null ? null : (int) round($metres / self::METRES_PER_MILE);
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
