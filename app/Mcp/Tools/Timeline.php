<?php

namespace App\Mcp\Tools;

use App\Datasets\Datasets;
use App\Models\Scopes\ListedScope;
use App\Models\TimelineEntry;
use App\Presenters\CardPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Entries for a date or a range, newest first, as the timeline shows them. Published only unless a status is asked for. Use search_entries instead when the question is about matching a condition rather than a period.')]
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
            'status' => ['nullable', 'in:published,unlisted,private,draft,all'],
        ]);

        $status = $input['status'] ?? 'published';

        if ($status === 'draft') {
            return Response::error('Drafts are undated, so no range holds them. Call search_entries with status draft.');
        }

        $to = $input['to'] ?? $input['from'];
        $query = TimelineEntry::query()->withoutGlobalScope(ListedScope::class)
            ->withCardRelations()
            ->whereBetween('occurred_at', [$input['from'].' 00:00:00', $to.' 23:59:59']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if (isset($input['type'])) {
            $dataset = Datasets::for($input['type']);

            if ($dataset === null) {
                return Response::error("No type called {$input['type']}. Call data_freshness to list them.");
            }

            $query->where('dataset', (new ($dataset->model()))->getMorphClass());
        }

        $entries = $query
            ->orderByInstant()
            ->limit($input['limit'] ?? self::MAX)
            ->get();

        $cards = $entries
            ->filter(fn (TimelineEntry $entry): bool => $entry->entry !== null)
            ->map(fn (TimelineEntry $entry): array => [
                'url' => $entry->entry->url(),
                'status' => $entry->entry->status->value,
                ...CardPresenter::for($entry->entry)->toArray(),
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
            'type' => $schema->string()->description('Limit to one type, e.g. sleep, activity, food, flight.'),
            'limit' => $schema->integer()->description('Entries to return, default and maximum '.self::MAX.'.'),
            'status' => $schema->string()->enum(['published', 'unlisted', 'private', 'all'])
                ->description('Default published. all includes unlisted and private.'),
        ];
    }
}
