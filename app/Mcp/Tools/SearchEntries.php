<?php

namespace App\Mcp\Tools;

use App\Enums\EntryStatus;
use App\Search\FilterValidator;
use App\Search\RunSearch;
use App\Search\SearchDrafts;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Search the timeline with a structured filter. The main way to find entries: call search_fields first for the types, fields and operators available. Published only unless a status is asked for; drafts come back in their own list, undated.')]
class SearchEntries extends Tool
{
    public function __construct(
        private readonly FilterValidator $validator,
        private readonly RunSearch $search,
        private readonly SearchDrafts $drafts,
    ) {}

    public function handle(Request $request): Response
    {
        $input = $request->validate([
            'filter' => ['required', 'array', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'order' => ['nullable', 'in:newest,oldest'],
            'status' => ['nullable', 'in:published,unlisted,private,draft,all'],
        ]);

        // Through the same validator the web search uses, so an unknown type,
        // field or operator is dropped here rather than reaching the compiler.
        $groups = ($this->validator)(json_encode($input['filter']));

        if ($groups === []) {
            return Response::error('No usable clause in that filter. Call search_fields for the types, fields and operators that exist.');
        }

        $status = $input['status'] ?? null;
        $namesStatus = collect($groups)->flatMap(fn (array $group): array => $group['conditions'])->contains('field', 'status');

        if ($status === null && $namesStatus) {
            return Response::error('The default of published would override that status condition. Pass status all to let each group\'s conditions decide.');
        }

        if ($status === 'draft') {
            $drafts = ($this->drafts)($groups);

            return Response::json(['total' => count($drafts), 'drafts' => $drafts]);
        }

        $spineStatus = match ($status) {
            null => EntryStatus::Published,
            'all' => null,
            default => EntryStatus::from($status),
        };

        $results = ($this->search)($groups, $input['page'] ?? 1, $input['order'] ?? 'newest', $spineStatus);

        if ($status === 'all') {
            $results['drafts'] = ($this->drafts)($groups);
        }

        return Response::json($results);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'filter' => $schema->array()
                ->description('Groups of conditions, OR between groups and AND within one. Each group is {"type": "sleep", "conditions": [{"field": "duration", "operator": "gt", "value": 28800}]}.')
                ->required(),
            'page' => $schema->integer()->description('1-based page of results, 25 per page.'),
            'order' => $schema->string()->description('"newest" (default) or "oldest".'),
            'status' => $schema->string()->enum(['published', 'unlisted', 'private', 'draft', 'all'])
                ->description('Default published. draft returns up to 25 undated drafts of the filtered types, newest edit first, in an unpaged drafts list; all returns every status plus that list, and lets status conditions in the filter pick per group.'),
        ];
    }
}
