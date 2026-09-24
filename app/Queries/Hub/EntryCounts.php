<?php

namespace App\Queries\Hub;

use App\Data\Hub\TypeCount;
use App\Datasets\Datasets;
use App\Models\TimelineEntry;
use Illuminate\Support\Carbon;

/**
 * Every data type, how many entries it has, and when the newest arrived.
 *
 * Counted off the timeline spine rather than the models: a food row is an item
 * of food and a food entry is a day of them, so counting models reports 25,922
 * food entries where there are 2,554.
 */
final class EntryCounts
{
    /**
     * @return list<TypeCount>
     */
    public function __invoke(): array
    {
        $spine = TimelineEntry::query()
            ->toBase()
            ->selectRaw('dataset, count(*) as total, max(occurred_at) as newest')
            ->groupBy('dataset')
            ->get()
            ->keyBy('dataset');

        $counts = [];

        foreach (Datasets::all() as $type => $dataset) {
            $row = $spine->get($type);

            $counts[] = new TypeCount(
                type: $type,
                label: $dataset->plural(),
                icon: $dataset->icon(),
                count: (int) ($row->total ?? 0),
                newest: $row?->newest === null ? null : Carbon::parse($row->newest),
                synced: $dataset->synced(),
                syncable: $dataset->syncCommand() !== null,
            );
        }

        // Synced first, then heaviest first inside each group, which is the
        // order the component draws without sorting again.
        usort($counts, fn (TypeCount $a, TypeCount $b): int => [$b->synced, $b->count] <=> [$a->synced, $a->count]);

        return $counts;
    }
}
