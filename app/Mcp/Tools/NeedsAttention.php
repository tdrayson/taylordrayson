<?php

namespace App\Mcp\Tools;

use App\Data\Hub\AttentionItem;
use App\Queries\Hub\NeedsAttention as NeedsAttentionQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Everything HQ says is waiting on a decision: comments and mentions held for moderation, failed jobs, books missing details, and drafts edited in the last month. Held items include what the person wrote.')]
class NeedsAttention extends Tool
{
    public function __construct(private readonly NeedsAttentionQuery $attention) {}

    public function handle(Request $request): Response
    {
        $items = ($this->attention)();

        return $items === []
            ? Response::text('Nothing needs attention.')
            : Response::json([
                'count' => count($items),
                'items' => array_map(fn (AttentionItem $item): array => Arr::except($item->toArray(), ['icon', 'actions']), $items),
            ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
