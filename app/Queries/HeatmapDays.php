<?php

namespace App\Queries;

use App\Models\TimelineEntry;
use Illuminate\Support\Carbon;

/**
 * Entries per day for the contribution heatmap, keyed yyyy-mm-dd.
 */
final class HeatmapDays
{
    /**
     * @return array<string, int>
     */
    public function __invoke(Carbon $start, Carbon $end): array
    {
        return TimelineEntry::query()
            ->toBase()
            ->selectRaw('DATE(occurred_at) as date, COUNT(*) as total')
            ->whereBetween('occurred_at', [$start, $end])
            ->groupBy('date')
            ->pluck('total', 'date')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }
}
