<?php

namespace App\Actions;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;

/**
 * Resolve where a Strava photo sits on the map, preferring the photo's own exact
 * `location` fix and falling back to {@see LocatePhotoOnRoute} against the GPS
 * stream for older or EXIF-stripped uploads.
 */
final class ResolvePhotoCoordinate
{
    public function __construct(private readonly LocatePhotoOnRoute $locate) {}

    /**
     * @param  array<string, mixed>  $photo  A raw Strava photo payload.
     * @param  array<int, int>  $timeStream  Elapsed seconds from start, ascending.
     * @param  array<int, array{0: float, 1: float}>  $latlngStream  Points parallel to $timeStream.
     * @return array{0: float, 1: float}|null The point as [lat, lng].
     */
    public function __invoke(
        array $photo,
        ?CarbonImmutable $activityStart = null,
        array $timeStream = [],
        array $latlngStream = [],
    ): ?array {
        return $this->locationFor($photo)
            ?? $this->fromStream($photo, $activityStart, $timeStream, $latlngStream);
    }

    /**
     * The photo's own GPS fix, when Strava supplied a valid one.
     *
     * Public so a caller can tell whether a photo needs the stream fallback
     * (and therefore whether a stream request is worth spending) before
     * fetching it.
     *
     * Guards against the [0, 0] "null island" placeholder that stripped EXIF
     * sometimes yields, which would otherwise drop a marker in the Atlantic.
     *
     * @param  array<string, mixed>  $photo
     * @return array{0: float, 1: float}|null
     */
    public function locationFor(array $photo): ?array
    {
        $location = $photo['location'] ?? null;

        if (! is_array($location) || count($location) !== 2) {
            return null;
        }

        [$lat, $lng] = array_values($location);

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        if ((float) $lat === 0.0 && (float) $lng === 0.0) {
            return null;
        }

        return [(float) $lat, (float) $lng];
    }

    /**
     * Interpolate position from the capture time against the GPS stream.
     *
     * @param  array<string, mixed>  $photo
     * @param  array<int, int>  $timeStream
     * @param  array<int, array{0: float, 1: float}>  $latlngStream
     * @return array{0: float, 1: float}|null
     */
    private function fromStream(
        array $photo,
        ?CarbonImmutable $activityStart,
        array $timeStream,
        array $latlngStream,
    ): ?array {
        $capturedAt = $photo['created_at'] ?? null;

        if ($activityStart === null || ! is_string($capturedAt) || $capturedAt === '') {
            return null;
        }

        try {
            $capturedTime = CarbonImmutable::parse($capturedAt);
        } catch (InvalidFormatException) {
            return null;
        }

        return ($this->locate)(
            $capturedTime,
            $activityStart,
            $timeStream,
            $latlngStream,
            LocatePhotoOnRoute::GRACE_SECONDS,
        );
    }
}
