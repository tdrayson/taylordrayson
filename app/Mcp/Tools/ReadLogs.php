<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use SplFileObject;

#[Description('Read the end of the application log, optionally keeping only lines containing a string.')]
class ReadLogs extends Tool
{
    private const DEFAULT_LINES = 50;

    private const MAX_LINES = 500;

    /** How far back to read before filtering, so a rare match is still found. */
    private const SCAN_LINES = 20000;

    public function handle(Request $request): Response
    {
        $input = $request->validate([
            'lines' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LINES],
            'contains' => ['nullable', 'string'],
        ]);

        $path = storage_path('logs/laravel.log');

        if (! is_readable($path)) {
            return Response::error('No log file to read.');
        }

        $matches = $this->tail($path, $input['contains'] ?? null);
        $lines = array_slice($matches, -($input['lines'] ?? self::DEFAULT_LINES));

        return Response::json(['lines' => count($lines), 'log' => implode("\n", $lines)]);
    }

    /**
     * The tail of the file, filtered. Read through SplFileObject rather than
     * file(), which would hold a multi-megabyte log in memory to return fifty
     * lines of it.
     *
     * @return list<string>
     */
    private function tail(string $path, ?string $contains): array
    {
        $file = new SplFileObject($path);
        $file->seek(PHP_INT_MAX);
        $from = max(0, $file->key() - self::SCAN_LINES);

        $lines = [];
        $file->seek($from);

        while (! $file->eof()) {
            $line = rtrim((string) $file->fgets(), "\r\n");

            if ($line !== '' && ($contains === null || str_contains($line, $contains))) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'lines' => $schema->integer()->description('How many matching lines to return, default '.self::DEFAULT_LINES.'.'),
            'contains' => $schema->string()->description('Keep only lines containing this string.'),
        ];
    }
}
