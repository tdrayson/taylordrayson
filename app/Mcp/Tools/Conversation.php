<?php

namespace App\Mcp\Tools;

use App\Presenters\Conversation as ConversationPresenter;
use App\Queries\EntryAtUrl;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Everything people have said about one entry, oldest first, as its page shows it: approved comments, webmentions, internal mentions, Strava and Swarm responses, and on-site reaction counts.')]
class Conversation extends Tool
{
    public function __construct(private readonly EntryAtUrl $entryAtUrl) {}

    public function handle(Request $request): Response
    {
        $url = $request->validate(['url' => ['required', 'string']])['url'];
        $model = $this->entryAtUrl->forPath($url);

        if ($model === null) {
            return Response::error('No entry at that URL. They look like /2026/08/26/sleep, and timeline returns them.');
        }

        return Response::json(ConversationPresenter::for($model)->toArray());
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()->description('The entry path, e.g. /2026/08/26/morning-run.')->required(),
        ];
    }
}
