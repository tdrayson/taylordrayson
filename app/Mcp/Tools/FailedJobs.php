<?php

namespace App\Mcp\Tools;

use App\Support\ReadOnlyDatabase;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List recently failed queue jobs: which job, when, and the error. Never the payload.')]
class FailedJobs extends Tool
{
    private const DEFAULT_LIMIT = 10;

    private const MAX_LIMIT = 50;

    public function __construct(private readonly ReadOnlyDatabase $database) {}

    public function handle(Request $request): Response
    {
        $limit = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
        ])['limit'] ?? self::DEFAULT_LIMIT;

        $jobs = $this->database->failedJobs($limit);

        return $jobs === []
            ? Response::text('No failed jobs.')
            : Response::json(['count' => count($jobs), 'jobs' => $jobs]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->description('How many to return, newest first, default '.self::DEFAULT_LIMIT.'.'),
        ];
    }
}
