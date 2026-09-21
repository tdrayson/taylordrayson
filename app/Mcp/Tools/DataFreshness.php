<?php

namespace App\Mcp\Tools;

use App\Datasets\Datasets;
use App\Models\TimelineEntry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Whether each kind of data is arriving: the newest entry of every type, when it was recorded, and how far behind that leaves it. Start here for "has today\'s X come through".')]
class DataFreshness extends Tool
{
    public function handle(Request $request): Response
    {
        $now = Carbon::now();
        $spine = self::spine();
        $newest = self::newest($spine);
        $types = [];

        foreach (Datasets::all() as $type => $dataset) {
            $entries = (int) ($spine[$type]->total ?? 0);
            $entry = $newest[$type] ?? null;

            if ($entry === null) {
                $types[] = ['type' => $type, 'label' => $dataset->plural(), 'entries' => $entries];

                continue;
            }

            $occurred = Carbon::parse($entry->occurred_at);
            $recorded = $entry->created_at ? Carbon::parse($entry->created_at) : null;

            $types[] = [
                'type' => $type,
                'label' => $dataset->plural(),
                'entries' => $entries,
                'newest' => $occurred->toDateTimeString(),
                'behind' => $occurred->diffForHumans($now, syntax: Carbon::DIFF_ABSOLUTE),
                'recorded' => $recorded?->toDateTimeString(),
                // The ingestion lag: how long after something happened it
                // reached the site, which is what a stalled sync shows up as.
                'recorded_after' => $recorded?->diffForHumans($occurred, syntax: Carbon::DIFF_ABSOLUTE),
            ];
        }

        usort($types, fn (array $a, array $b): int => ($a['newest'] ?? '') <=> ($b['newest'] ?? ''));

        return Response::json(['as_of' => $now->toDateTimeString(), 'types' => $types]);
    }

    /**
     * Count and newest date per type, off the timeline spine rather than the
     * models.
     *
     * A food row is an item of food and a food entry is a day of them, so
     * counting models reported 25,922 food entries where there are 2,554.
     *
     * @return array<string, object{total: int, newest: string}>
     */
    private static function spine(): array
    {
        return TimelineEntry::query()
            ->toBase()
            ->selectRaw('dataset, count(*) as total, max(occurred_at) as newest')
            ->groupBy('dataset')
            ->get()
            ->keyBy('dataset')
            ->all();
    }

    /**
     * The newest entry of each type, for its `created_at`.
     *
     * Fetched by the handful of timestamps the aggregate already found, rather
     * than a correlated subquery per row, which on ten thousand entries does
     * not finish.
     *
     * @param  array<string, object>  $spine
     * @return array<string, TimelineEntry>
     */
    private static function newest(array $spine): array
    {
        $dates = array_values(array_unique(array_map(fn (object $row): string => $row->newest, $spine)));

        if ($dates === []) {
            return [];
        }

        $rows = TimelineEntry::query()->whereIn('occurred_at', $dates)->get();
        $newest = [];

        // A timestamp can be the newest for one type and an ordinary entry of
        // another, so each row has to match its own type's maximum.
        foreach ($rows as $row) {
            $type = (string) $row->dataset;

            if (isset($spine[$type]) && $row->occurred_at->toDateTimeString() === $spine[$type]->newest) {
                $newest[$type] = $row;
            }
        }

        return $newest;
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
