<?php

namespace App\Mcp\Tools;

use App\Data\Hub\ResponseItem;
use App\Queries\Hub\RecentResponses as RecentResponsesQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('The latest things people have said or done across the site, newest first: comments, webmentions, reactions, and Strava and Swarm responses. Use conversation for everything on one entry.')]
class RecentResponses extends Tool
{
    private const DEFAULT_LIMIT = 10;

    private const MAX_LIMIT = 50;

    public function __construct(private readonly RecentResponsesQuery $responses) {}

    public function handle(Request $request): Response
    {
        $input = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
            'since' => ['nullable', 'date'],
        ]);

        // Never HQ's own last-visit stamp: reading here must not move what HQ shows as new.
        $since = isset($input['since']) ? Carbon::parse($input['since']) : null;
        $items = ($this->responses)($input['limit'] ?? self::DEFAULT_LIMIT, $since);

        return $items === []
            ? Response::text('No responses yet.')
            : Response::json([
                'count' => count($items),
                'responses' => array_map(fn (ResponseItem $item): array => Arr::except($item->toArray(), ['icon', 'markable']), $items),
            ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->description('How many to return, default '.self::DEFAULT_LIMIT.', maximum '.self::MAX_LIMIT.'.'),
            'since' => $schema->string()->description('A date or datetime; responses after it are marked isNew. Omit and every one is.'),
        ];
    }
}
