<?php

namespace App\Mcp\Tools;

use App\Presenters\Exports\NowExport;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Right now, as the /now page shows it: where I am, the weather, today\'s activity rings, battery, what I\'m reading and last night\'s sleep.')]
class Now extends Tool
{
    public function handle(Request $request): Response
    {
        return Response::json((new NowExport)->present()->toArray());
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
