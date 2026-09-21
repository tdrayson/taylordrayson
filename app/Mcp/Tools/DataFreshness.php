<?php

namespace App\Mcp\Tools;

use App\Data\Hub\TypeCount;
use App\Models\TimelineEntry;
use App\Queries\Hub\EntryCounts;
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
        $counts = collect(app(EntryCounts::class)())->keyBy('type');
        $newest = self::newest($counts->map(
            fn (TypeCount $count): ?string => $count->newest?->toDateTimeString()
        )->filter()->all());

        $types = [];

        foreach ($counts as $type => $count) {
            $entry = $newest[$type] ?? null;

            if ($entry === null) {
                $types[] = ['type' => $type, 'label' => $count->label, 'entries' => $count->count];

                continue;
            }

            $occurred = Carbon::parse($entry->occurred_at);
            $recorded = $entry->created_at ? Carbon::parse($entry->created_at) : null;

            $types[] = [
                'type' => $type,
                'label' => $count->label,
                'entries' => $count->count,
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
     * The newest entry of each type, for its `created_at`.
     *
     * @param  array<string, string>  $dates  Newest occurred_at, keyed by type.
     * @return array<string, TimelineEntry>
     */
    private static function newest(array $dates): array
    {
        if ($dates === []) {
            return [];
        }

        $rows = TimelineEntry::query()->whereIn('occurred_at', array_values(array_unique($dates)))->get();
        $newest = [];

        // A timestamp can be the newest for one type and an ordinary entry of
        // another, so each row has to match its own type's maximum.
        foreach ($rows as $row) {
            $type = (string) $row->dataset;

            if (isset($dates[$type]) && $row->occurred_at->toDateTimeString() === $dates[$type]) {
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
