<?php

namespace App\Mcp\Tools;

use App\Models\TimelineEntry;
use App\Presenters\CardPresenter;
use App\Timeline\TypeRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Entries for a date or a range, newest first, as the timeline shows them. Use search_entries instead when the question is about matching a condition rather than a period.')]
class Timeline extends Tool
{
    private const MAX = 100;

    public function handle(Request $request): Response
    {
        $input = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'type' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX],
        ]);

        $to = $input['to'] ?? $input['from'];
        $query = TimelineEntry::query()
            ->withCardRelations()
            ->whereBetween('occurred_at', [$input['from'].' 00:00:00', $to.' 23:59:59']);

        if (isset($input['type'])) {
            $definition = TypeRegistry::find($input['type']);

            if ($definition === null) {
                return Response::error("No type called {$input['type']}. Call data_freshness to list them.");
            }

            $query->where('timelineable_type', (new $definition['model'])->getMorphClass());
        }

        $entries = $query
            ->orderByInstant()
            ->limit($input['limit'] ?? self::MAX)
            ->get();

        $cards = $entries
            ->filter(fn (TimelineEntry $entry): bool => $entry->timelineable !== null)
            ->map(fn (TimelineEntry $entry): array => [
                'url' => $entry->timelineable->url(),
                ...CardPresenter::for($entry->timelineable)->toArray(),
            ])
            ->values();

        return Response::json(['from' => $input['from'], 'to' => $to, 'count' => $cards->count(), 'entries' => $cards]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('Start date, YYYY-MM-DD.')->required(),
            'to' => $schema->string()->description('End date, YYYY-MM-DD. Omit for a single day.'),
            'type' => $schema->string()->description('Limit to one type, e.g. sleep, activity, calorie, flight.'),
            'limit' => $schema->integer()->description('Entries to return, default and maximum '.self::MAX.'.'),
        ];
    }
}
