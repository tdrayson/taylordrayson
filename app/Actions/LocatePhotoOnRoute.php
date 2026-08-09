<?php

namespace App\Actions;

use Carbon\CarbonImmutable;

/**
 * Resolve where along a route a photo was taken, looking its offset from the
 * activity start up in the `time` stream and returning the matching `latlng`.
 * The fallback for {@see ResolvePhotoCoordinate}. Pure maths, no IO.
 */
class LocatePhotoOnRoute
{
    /**
     * How far outside the GPS stream, in seconds, a photo may still be clamped to
     * the nearest endpoint. Covers finish-line and trailhead photos taken just
     * before recording started or just after it stopped.
     */
    public const GRACE_SECONDS = 180;

    /**
     * The coordinate for a photo, or null when it cannot be placed on the route.
     *
     * @param  array<int, int>  $timeStream  Elapsed seconds from the activity start, ascending.
     * @param  array<int, array{0: float, 1: float}>  $latlngStream  Points as [lat, lng], parallel to $timeStream.
     * @param  int  $toleranceSeconds  How far outside the stream bounds an offset may still be clamped to the nearest endpoint.
     * @return array{0: float, 1: float}|null The point as [lat, lng].
     */
    public function __invoke(
        CarbonImmutable $capturedAt,
        CarbonImmutable $activityStart,
        array $timeStream,
        array $latlngStream,
        int $toleranceSeconds = 0,
    ): ?array {
        if ($timeStream === [] || $latlngStream === []) {
            return null;
        }

        $offset = $capturedAt->getTimestamp() - $activityStart->getTimestamp();
        $last = count($timeStream) - 1;

        if ($offset < $timeStream[0] - $toleranceSeconds || $offset > $timeStream[$last] + $toleranceSeconds) {
            return null;
        }

        return $latlngStream[$this->nearestIndex($timeStream, $offset)] ?? null;
    }

    /**
     * The index of the stream sample closest to the given offset. Ties resolve to
     * the earlier sample, so the result is deterministic.
     *
     * @param  array<int, int>  $timeStream
     */
    private function nearestIndex(array $timeStream, int $offset): int
    {
        $low = 0;
        $high = count($timeStream) - 1;

        while ($low < $high) {
            $mid = intdiv($low + $high, 2);

            if ($timeStream[$mid] < $offset) {
                $low = $mid + 1;
            } else {
                $high = $mid;
            }
        }

        if ($low > 0 && ($offset - $timeStream[$low - 1]) <= ($timeStream[$low] - $offset)) {
            return $low - 1;
        }

        return $low;
    }
}
