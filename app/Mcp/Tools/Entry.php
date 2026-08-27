<?php

namespace App\Mcp\Tools;

use App\Models\TimelineEntry;
use App\Presenters\CardPresenter;
use App\Support\EntryColumns;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('One entry in full: its card plus every value the card leaves out, such as a sleep\'s score components. Sampled series are named with their sizes rather than returned; fetch one with entry_series.')]
class Entry extends Tool
{
    public function __construct(private readonly EntryColumns $columns) {}

    public function handle(Request $request): Response
    {
        $model = $this->find($request);

        if ($model === null) {
            return Response::error('No entry at that URL. They look like /2026/08/26/sleep, and timeline returns them.');
        }

        return Response::json([
            'url' => $model->url(),
            ...CardPresenter::for($model)->toArray(),
            ...$this->columns->scalars($model),
            // Named and measured rather than returned: one activity's track,
            // altitude and speed together are most of a context window.
            'series' => $this->columns->series($model),
        ]);
    }

    /** The entry behind a `/YYYY/MM/DD/slug` path, or null when there is none. */
    public static function resolve(string $url): ?Model
    {
        if (preg_match('#(\d{4})/(\d{2})/(\d{2})/([a-z0-9-]+)#i', $url, $parts) !== 1) {
            return null;
        }

        [, $year, $month, $day, $slug] = $parts;

        $entry = TimelineEntry::query()
            ->with('timelineable')
            ->whereDate('occurred_at', "{$year}-{$month}-{$day}")
            ->where('url_slug', $slug)
            ->first();

        $entry?->timelineable?->setRelation('timelineEntry', $entry);

        return $entry?->timelineable;
    }

    private function find(Request $request): ?Model
    {
        return self::resolve($request->validate(['url' => ['required', 'string']])['url']);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()->description('The entry path, e.g. /2026/08/26/sleep.')->required(),
        ];
    }
}
