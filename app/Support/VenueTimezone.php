<?php

namespace App\Support;

use App\Services\TimeApi;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The IANA timezone a coordinate sits in, resolved once and remembered.
 *
 * Check-ins are the only type that always knows where it was, so their zone
 * comes from the venue rather than from the server or the phone.
 */
class VenueTimezone
{
    /**
     * Coordinates are rounded before lookup, so the hundreds of check-ins at
     * the same handful of places share one answer. Two decimal places is about
     * 1.1km: far fewer lookups than rows, and only a venue sitting on a
     * timezone border could land on the wrong side.
     */
    private const PLACES = 2;

    private const TTL = 60 * 60 * 24 * 365;

    public function __construct(private readonly TimeApi $timeApi) {}

    /** Null when there is no coordinate to ask about, or the lookup fails. */
    public function forCoordinate(float|string|null $latitude, float|string|null $longitude): ?string
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        $lat = round((float) $latitude, self::PLACES);
        $lng = round((float) $longitude, self::PLACES);

        return Cache::remember("venue-timezone:{$lat},{$lng}", self::TTL, function () use ($lat, $lng): ?string {
            try {
                return $this->timeApi->timezoneForCoordinate($lat, $lng);
            } catch (Throwable) {
                // A lookup that cannot be made leaves the entry as it was, so a
                // flaky network slows a backfill rather than failing it.
                return null;
            }
        });
    }

    /**
     * A UTC timestamp as the wall clock read where it happened.
     *
     * With no zone this falls back to home, which is what the column meant
     * before any check-in carried one.
     */
    public function localWallClock(int $timestamp, ?string $timezone): string
    {
        return Carbon::createFromTimestampUTC($timestamp)
            ->setTimezone(EntryInstant::zone($timezone))
            ->format('Y-m-d H:i:s');
    }
}
