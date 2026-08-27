<?php

namespace App\Mcp\Tools;

use App\Search\SearchSchema;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('What search_entries can filter on: every type, its fields, each field\'s operators, and the values an enumerated field accepts.')]
class SearchFields extends Tool
{
    public function handle(Request $request): Response
    {
        $type = $request->validate(['type' => ['nullable', 'string']])['type'] ?? null;

        $schema = SearchSchema::forClient();

        if ($type === null) {
            return Response::json(['types' => $schema]);
        }

        $match = collect($schema)->firstWhere('type', $type);

        return $match === null
            ? Response::error("No searchable type called {$type}. Call this tool with no arguments to list them.")
            : Response::json($match);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->description('Omit to list every type. The whole schema is large, so name a type when you know it.'),
        ];
    }
}
