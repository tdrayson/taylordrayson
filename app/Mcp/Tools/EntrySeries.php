<?php

namespace App\Mcp\Tools;

use App\Support\EntryColumns;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('One sampled series from an entry: a GPS track, an altitude or speed profile, a night\'s sleep stages. Large, so call entry first to see which exist and what each costs.')]
class EntrySeries extends Tool
{
    public function __construct(private readonly EntryColumns $columns) {}

    public function handle(Request $request): Response
    {
        $input = $request->validate([
            'url' => ['required', 'string'],
            'series' => ['required', 'string'],
        ]);

        $model = Entry::resolve($input['url']);

        if ($model === null) {
            return Response::error('No entry at that URL.');
        }

        $available = $this->columns->series($model);

        if (! array_key_exists($input['series'], $available)) {
            $names = implode(', ', array_keys($available)) ?: 'none';

            return Response::error("That entry has no {$input['series']} series. It has: {$names}.");
        }

        return Response::json([
            'url' => $model->url(),
            'series' => $input['series'],
            'values' => $this->columns->value($model, $input['series']),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()->description('The entry path, e.g. /2026/08/26/sleep.')->required(),
            'series' => $schema->string()->description('The series name, as listed by the entry tool.')->required(),
        ];
    }
}
