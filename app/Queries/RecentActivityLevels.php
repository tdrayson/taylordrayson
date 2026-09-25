<?php

namespace App\Queries;

/**
 * The last few days of timeline entries as heatmap levels 0-3, scaled to the busiest of those days.
 */
final class RecentActivityLevels
{
    private const TOP_LEVEL = 3;

    public function __construct(private readonly HeatmapDays $heatmapDays) {}

    /**
     * @param  int  $days  How many days to cover, ending today.
     * @return list<int> One level per day, oldest first.
     */
    public function __invoke(int $days): array
    {
        $start = today()->subDays($days - 1);
        $counts = ($this->heatmapDays)($start, today()->endOfDay());
        $peak = max([0, ...array_values($counts)]);

        return array_map(function (int $offset) use ($start, $counts, $peak): int {
            $count = $counts[$start->copy()->addDays($offset)->toDateString()] ?? 0;

            return $count === 0 ? 0 : max(1, (int) ceil($count / $peak * self::TOP_LEVEL));
        }, range(0, $days - 1));
    }
}
