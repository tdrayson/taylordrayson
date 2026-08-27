<?php

namespace App\Mcp\Tools;

use App\Search\FilterValidator;
use App\Search\RunSearch;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Search the timeline with a structured filter. The main way to find entries: call search_fields first for the types, fields and operators available.')]
class SearchEntries extends Tool
{
    public function __construct(
        private readonly FilterValidator $validator,
        private readonly RunSearch $search,
    ) {}

    public function handle(Request $request): Response
    {
        $input = $request->validate([
            'filter' => ['required', 'array', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'order' => ['nullable', 'in:newest,oldest'],
        ]);

        // Through the same validator the web search uses, so an unknown type,
        // field or operator is dropped here rather than reaching the compiler.
        $groups = ($this->validator)(json_encode($input['filter']));

        if ($groups === []) {
            return Response::error('No usable clause in that filter. Call search_fields for the types, fields and operators that exist.');
        }

        $results = ($this->search)($groups, $input['page'] ?? 1, $input['order'] ?? 'newest');

        return Response::json($results);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'filter' => $schema->array()
                ->description('Groups of conditions, OR between groups and AND within one. Each group is {"type": "sleep", "conditions": [{"field": "duration", "operator": "gt", "value": 28800}]}.')
                ->required(),
            'page' => $schema->integer()->description('1-based page of results, 25 per page.'),
            'order' => $schema->string()->description('"newest" (default) or "oldest".'),
        ];
    }
}
