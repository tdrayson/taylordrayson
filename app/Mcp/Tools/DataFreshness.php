<?php

namespace App\Mcp\Tools;

use App\Timeline\TypeRegistry;
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
        $types = [];

        foreach (TypeRegistry::all() as $type => $definition) {
            $model = $definition['model'];

            /** @var object|null $newest */
            $newest = $model::query()->orderByDesc('occurred_at')->first();

            if ($newest === null) {
                $types[] = ['type' => $type, 'label' => $definition['label'], 'entries' => 0];

                continue;
            }

            $occurred = Carbon::parse($newest->occurred_at);
            $recorded = $newest->created_at ? Carbon::parse($newest->created_at) : null;

            $types[] = [
                'type' => $type,
                'label' => $definition['label'],
                'entries' => $model::query()->count(),
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
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
