<?php

namespace App\Actions;

use Carbon\CarbonImmutable;

/**
 * Resolve where along a route a photo was taken, by matching its capture time
 * against an activity's GPS stream.
 *
 * Strava photos carry no coordinates and their EXIF is stripped, so position is
 * inferred: the photo's offset from the activity start is looked up in the
 * `time` stream, and the matching `latlng` sample is returned. Pure maths with
 * no HTTP or database access.
 */
class LocatePhotoOnRoute
{
    /**
     * The coordinate for a photo, or null when it cannot be placed on the route.
     *
     * @param  array<int, int>  $timeStream  Elapsed seconds from the activity start, ascending.
     * @param  array<int, array{0: float, 1: float}>  $latlngStream  Points as [lat, lng], parallel to $timeStream.
     * @return array{0: float, 1: float}|null The point as [lat, lng].
     */
    public function __invoke(
        CarbonImmutable $capturedAt,
        CarbonImmutable $activityStart,
        array $timeStream,
        array $latlngStream,
    ): ?array {
        if ($timeStream === [] || $latlngStream === []) {
            return null;
        }

        $offset = $capturedAt->getTimestamp() - $activityStart->getTimestamp();
        $last = count($timeStream) - 1;

        if ($offset < $timeStream[0] || $offset > $timeStream[$last]) {
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
