<?php

namespace App\Mcp\Tools;

use App\Queries\PeriodStats;
use App\Queries\StatsForType;
use App\Timeline\TypeRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Totals and averages over a period: for one type, or across everything when no type is given. The same figures the stats pages show.')]
class Stats extends Tool
{
    public function __construct(
        private readonly StatsForType $forType,
        private readonly PeriodStats $forPeriod,
    ) {}

    public function handle(Request $request): Response
    {
        $input = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
            'type' => ['nullable', 'string'],
        ]);

        $start = Carbon::parse($input['from'])->startOfDay();
        $end = Carbon::parse($input['to'])->endOfDay();

        if (! isset($input['type'])) {
            return Response::json(($this->forPeriod)($start, $end));
        }

        if (TypeRegistry::find($input['type']) === null) {
            return Response::error("No type called {$input['type']}. Call data_freshness to list them.");
        }

        return Response::json(($this->forType)($input['type'], $start, $end, 'previous-period'));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('Start date, YYYY-MM-DD.')->required(),
            'to' => $schema->string()->description('End date, YYYY-MM-DD.')->required(),
            'type' => $schema->string()->description('One type, e.g. activity or sleep. Omit for everything.'),
        ];
    }
}
