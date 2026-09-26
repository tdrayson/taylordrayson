<?php

namespace App\Mcp\Tools;

use App\Enums\ExportFormat;
use App\Presenters\CardPresenter;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;
use App\Queries\EntryAtUrl;
use App\Support\EntryColumns;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('One entry in full: its card plus every value the card leaves out, such as a sleep\'s score components. Sampled series are named with their sizes rather than returned; fetch one with entry_series. Pass a format such as md to read an article or note\'s body as text.')]
class Entry extends Tool
{
    public function __construct(
        private readonly EntryColumns $columns,
        private readonly EntryAtUrl $entryAtUrl,
    ) {}

    public function handle(Request $request): Response
    {
        $input = $request->validate([
            'url' => ['required', 'string'],
            'format' => ['nullable', 'string', 'in:'.implode(',', array_column(ExportFormat::cases(), 'value'))],
        ]);

        $model = $this->entryAtUrl->forPath($input['url']);

        if ($model === null) {
            return Response::error('No entry at that URL. They look like /2026/08/26/sleep, which timeline returns, or /drafts/article/12, which search_entries returns.');
        }

        if (isset($input['format'])) {
            return $this->export($model, ExportFormat::from($input['format']));
        }

        return Response::json([
            'url' => $model->url(),
            'status' => $model->status->value,
            ...CardPresenter::for($model)->toArray(),
            ...$this->columns->scalars($model),
            // Named and measured rather than returned: one activity's track,
            // altitude and speed together are most of a context window.
            'series' => $this->columns->series($model),
        ]);
    }

    /** The entry rendered as its export route renders it, e.g. `/2026/08/26/slug.md`. */
    private function export(Model $model, ExportFormat $format): Response
    {
        $data = ExportPresenter::for($model);
        $renderer = Formats::find($data, $format);

        if ($renderer === null) {
            $available = implode(', ', array_keys(Formats::for($data)));

            return Response::error("That entry has no {$format->value} format. It has: {$available}.");
        }

        return Response::text($renderer->render($data));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()->description('The entry path, e.g. /2026/08/26/sleep.')->required(),
            'format' => $schema->string()
                ->enum(array_column(ExportFormat::cases(), 'value'))
                ->description('Return the entry as this export format instead of its card. md reads a long body as text.'),
        ];
    }
}
