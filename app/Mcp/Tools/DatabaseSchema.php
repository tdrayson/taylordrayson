<?php

namespace App\Mcp\Tools;

use App\Support\ReadOnlyDatabase;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List the database tables with their row counts, or the columns of one table.')]
class DatabaseSchema extends Tool
{
    public function __construct(private readonly ReadOnlyDatabase $database) {}

    public function handle(Request $request): Response
    {
        $table = $request->validate([
            'table' => ['nullable', 'string'],
        ])['table'] ?? null;

        if ($table === null) {
            return Response::json(['tables' => $this->database->tables()]);
        }

        $columns = $this->database->columns($table);

        // Distinguishable from a table that exists and has no columns, which
        // cannot happen: an empty list here means the name was wrong.
        return $columns === []
            ? Response::error("No table named {$table}. Call this tool with no arguments to list them.")
            : Response::json(['table' => $table, 'columns' => $columns]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'table' => $schema->string()->description('Omit to list every table.'),
        ];
    }
}
