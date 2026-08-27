<?php

namespace App\Mcp\Tools;

use App\Support\ReadOnlyDatabase;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use RuntimeException;

#[Description('Fallback: run one read-only SELECT when no other tool can answer it. Prefer search_entries, timeline, entry or stats, which need no knowledge of the schema.')]
class DatabaseQuery extends Tool
{
    public function __construct(private readonly ReadOnlyDatabase $database) {}

    public function handle(Request $request): Response
    {
        ['sql' => $sql, 'limit' => $limit] = $request->validate([
            'sql' => ['required', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.ReadOnlyDatabase::MAX_LIMIT],
        ]) + ['limit' => null];

        // Every read is recorded: the point of the audit trail is that it exists
        // even for the queries nobody thought were worth recording.
        Log::info('mcp query', ['sql' => $sql, 'user' => $request->user()?->getAuthIdentifier()]);

        try {
            $rows = $this->database->select($sql, $limit ?? ReadOnlyDatabase::DEFAULT_LIMIT);
        } catch (RuntimeException $refused) {
            return Response::error($refused->getMessage());
        }

        return Response::json(['count' => count($rows), 'rows' => $rows]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'sql' => $schema->string()
                ->description('A single SELECT statement. Writes, multiple statements, ATTACH and PRAGMA are refused.')
                ->required(),
            'limit' => $schema->integer()
                ->description('Rows to return, default '.ReadOnlyDatabase::DEFAULT_LIMIT.', maximum '.ReadOnlyDatabase::MAX_LIMIT.'.'),
        ];
    }
}
