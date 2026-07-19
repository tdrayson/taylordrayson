<?php

namespace App\Support;

/**
 * Evenly-spaced index selection for downsampling a series to a point cap.
 * Returning indices (rather than a reduced array) lets several parallel
 * series be sampled by the SAME indices so they stay aligned point-for-point.
 */
class Downsample
{
    /**
     * @return array<int, int>
     */
    public static function indices(int $total, int $cap): array
    {
        if ($total === 0) {
            return [];
        }

        if ($cap === 0 || $total <= $cap) {
            return range(0, max($total - 1, 0));
        }

        if ($cap === 1) {
            return [0];
        }

        $step = ($total - 1) / ($cap - 1);
        $indices = [];

        for ($index = 0; $index < $cap; $index++) {
            $indices[] = (int) round($index * $step);
        }

        return $indices;
    }
}
