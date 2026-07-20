<?php

namespace App\Http\Controllers;

use App\Actions\BuildTimelineFeed;
use App\Models\TimelineEntry;
use App\Search\SearchCompiler;
use App\Search\SearchPresets;
use App\Search\SearchSchema;
use App\Support\OgMeta;
use App\Timeline\TypeRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    private const LIMIT = 8;

    private const DESTINATION_LIMIT = 6;

    private const PER_TYPE = 5;

    private const PER_PAGE = 25;

    /**
     * Searchable text columns per timeline type. Types without meaningful free
     * text (e.g. sleep) are intentionally omitted.
     *
     * @var array<string, array<int, string>>
     */
    private const SEARCHABLE = [
        'activity' => ['name'],
        'calorie' => ['name', 'meal'],
        'media' => ['title'],
        'event' => ['name', 'venue_name', 'city', 'country'],
        'appearance' => ['title', 'show_name', 'description'],
        'podcast' => ['topic', 'show_notes'],
        'flight' => ['flight_number', 'origin_iata', 'destination_iata', 'reason'],
        'checkin' => ['venue_name', 'category', 'city', 'description'],
        'fuel' => ['station', 'city'],
        'project' => ['title', 'description', 'status'],
        'note' => ['content'],
        'article' => ['title', 'excerpt', 'content'],
    ];

    /**
     * @param  BuildTimelineFeed  $feed  Shapes entries into feed-card day groups.
     * @param  SearchCompiler  $compiler  Compiles a filter into a TimelineEntry query.
     */
    public function __construct(
        private readonly BuildTimelineFeed $feed,
        private readonly SearchCompiler $compiler,
    ) {}

    /**
     * Render the advanced search page: a grouped (OR-between, AND-within) query
     * builder compiled from a URL-encoded filter, with feed-card results.
     *
     * @param  Request  $request  The incoming request (carries `filter` and `page`).
     * @return Response The Inertia response for the Search page.
     */
    public function index(Request $request): Response
    {
        $groups = $this->validateFilter($request->input('filter'));
        $page = max(1, (int) $request->input('page', 1));
        $order = $request->input('order') === 'oldest' ? 'oldest' : 'newest';
        $results = $this->runSearch($groups, $page, $order);

        return Inertia::render('Search', [
            'og' => OgMeta::search(),
            'schema' => SearchSchema::forClient(),
            'presets' => SearchPresets::all(),
            'filter' => $groups,
            'order' => $order,
            'groups' => $results['groups'],
            'total' => $results['total'],
            'currentPage' => $results['currentPage'],
            'lastPage' => $results['lastPage'],
        ]);
    }

    /**
     * Run the compiled filter and shape the requested page of results for the feed.
     *
     * @param  array<int, array<string, mixed>>  $groups  Validated filter groups.
     * @param  int  $page  The 1-based results page to load.
     * @param  string  $order  'newest' (default) or 'oldest', by occurrence.
     * @return array{groups: array<int, mixed>, total: int, currentPage: int, lastPage: int}
     */
    private function runSearch(array $groups, int $page, string $order = 'newest'): array
    {
        if ($groups === []) {
            return ['groups' => [], 'total' => 0, 'currentPage' => 1, 'lastPage' => 1];
        }

        $query = TimelineEntry::query()
            ->withCardRelations();

        $this->compiler->apply($query, $groups);

        $paginated = $query
            ->orderBy('occurred_at', $order === 'oldest' ? 'asc' : 'desc')
            ->paginate(self::PER_PAGE, ['*'], 'page', $page);

        return [
            'groups' => $this->feed->groupByDay(collect($paginated->items())),
            'total' => $paginated->total(),
            'currentPage' => $paginated->currentPage(),
            'lastPage' => $paginated->lastPage(),
        ];
    }

    /**
     * Decode and whitelist the URL-encoded filter against the schema, dropping any
     * unknown type, field or operator so only safe, known clauses reach the compiler.
     *
     * @param  mixed  $raw  The raw `filter` query value (expected to be a JSON string).
     * @return array<int, array{type: string, conditions: array<int, array{field: string, operator: string, value: mixed}>}>
     */
    private function validateFilter(mixed $raw): array
    {
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        $schema = SearchSchema::types();

        return collect($decoded)
            ->map(fn (mixed $group): ?array => $this->validateGroup($schema, $group))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Validate one group, returning null when its type is unknown or it has no
     * surviving conditions.
     *
     * @param  array<string, mixed>  $schema  The full schema keyed by type.
     * @param  mixed  $group  A candidate group from the decoded filter.
     * @return array{type: string, conditions: array<int, array{field: string, operator: string, value: mixed}>}|null
     */
    private function validateGroup(array $schema, mixed $group): ?array
    {
        $type = is_array($group) ? ($group['type'] ?? null) : null;

        if (! isset($schema[$type]) || ! isset($group['conditions']) || ! is_array($group['conditions'])) {
            return null;
        }

        $conditions = $this->validateConditions($schema[$type]['fields'], $group['conditions']);

        return $conditions === [] ? null : ['type' => $type, 'conditions' => $conditions];
    }

    /**
     * Keep only conditions whose field exists and whose operator is allowed for it.
     *
     * @param  array<string, array<string, mixed>>  $fields  The type's field definitions.
     * @param  array<int, mixed>  $conditions  Candidate conditions from the filter.
     * @return array<int, array{field: string, operator: string, value: mixed}>
     */
    private function validateConditions(array $fields, array $conditions): array
    {
        return collect($conditions)
            ->filter(fn (mixed $condition): bool => is_array($condition))
            ->map(function (array $condition) use ($fields): ?array {
                $field = $fields[$condition['field'] ?? null] ?? null;
                $operator = $condition['operator'] ?? null;

                if ($field === null || ! in_array($operator, $field['operators'], true)) {
                    return null;
                }

                return [
                    'field' => $condition['field'],
                    'operator' => $operator,
                    'value' => $condition['value'] ?? null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Free-text search across timeline entries for the command palette, plus the
     * matching taxonomy destination pages, ordered by recency.
     *
     * @param  Request  $request  Carries the `q` query term.
     * @return JsonResponse The matches as { results: [...], destinations: [...] }.
     */
    public function suggest(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['results' => [], 'destinations' => []]);
        }

        $registry = TypeRegistry::all();

        $results = collect(array_keys(self::SEARCHABLE))
            ->flatMap(fn (string $key): array => $this->searchType(
                $registry[$key]['model'],
                $key,
                self::SEARCHABLE[$key],
                $term,
            ))
            ->sortByDesc(fn (array $result): int => $result['occurred_at']->getTimestamp())
            ->take(self::LIMIT)
            ->map(fn (array $result): array => [
                'title' => $result['title'],
                'subtitle' => $result['subtitle'],
                'type' => $result['type'],
                'url' => $result['url'],
                'date' => $result['occurred_at']->format('j M Y'),
            ])
            ->values();

        return response()->json([
            'results' => $results,
            'destinations' => $this->matchDestinations($term),
        ]);
    }

    /**
     * Taxonomy pages (e.g. /activities/run, /places/london) whose label matches
     * the term, drawn live from the registry so newly logged values appear with
     * no code change. Prefix hits rank above mid-word hits, then shorter labels.
     *
     * @param  string  $term  The free-text query.
     * @return array<int, array{label: string, section: string, type: string, tag: bool, url: string}>
     */
    private function matchDestinations(string $term): array
    {
        $needle = Str::lower($term);

        return collect(TypeRegistry::all())
            ->flatMap(function (array $definition, string $type) use ($needle): array {
                $taxonomy = $definition['taxonomy'];

                if ($taxonomy === null) {
                    return [];
                }

                return $taxonomy['values']()
                    ->filter(fn (array $value): bool => str_contains(Str::lower($value['label']), $needle))
                    ->map(fn (array $value): array => [
                        'label' => $value['label'],
                        // The taxonomy's own kind label ("Tag", "Type", "Category", …),
                        // not the owning type's label: a tag jump is a "Tag", not an
                        // "Articles" entry, and a run is an activity "Type", not an "Activities" one.
                        'section' => $taxonomy['label'],
                        'type' => $type,
                        // Tags span every type, so the palette shows them with a tag
                        // icon rather than the owning model's icon.
                        'tag' => $taxonomy['param'] === 'tag',
                        // Tags jump to the cross-type /tags feed, not a per-type filter,
                        // so the same tag on several types collapses to one destination
                        // (deduped by url below). Other taxonomies keep their type page.
                        'url' => $taxonomy['param'] === 'tag'
                            ? '/tags/'.$value['value']
                            : '/'.$taxonomy['base'].'/'.$value['value'],
                        'rank' => str_starts_with(Str::lower($value['label']), $needle) ? 0 : 1,
                    ])
                    ->all();
            })
            ->sortBy(fn (array $destination): string => sprintf('%d %03d %s', $destination['rank'], mb_strlen($destination['label']), Str::lower($destination['label'])))
            ->unique('url')
            ->take(self::DESTINATION_LIMIT)
            ->map(fn (array $destination): array => [
                'label' => $destination['label'],
                'section' => $destination['section'],
                'type' => $destination['type'],
                'tag' => $destination['tag'],
                'url' => $destination['url'],
            ])
            ->values()
            ->all();
    }

    /**
     * Match a single type's text columns and shape each hit for the palette.
     *
     * @param  class-string  $model
     * @param  array<int, string>  $columns
     * @return array<int, array{title: string, subtitle: ?string, type: string, url: string, occurred_at: Carbon}>
     */
    private function searchType(string $model, string $type, array $columns, string $term): array
    {
        $query = $model::query();

        $this->compiler->guardPublished($query, $model);

        $query->where(function (Builder $builder) use ($columns, $term): void {
            foreach ($columns as $column) {
                $builder->orWhere($column, 'like', "%{$term}%");
            }
        })
            ->orderByDesc('occurred_at')
            ->limit(self::PER_TYPE);

        if ($type === 'flight') {
            $query->with(['origin', 'destination', 'airline']);
        }

        return $query->get()
            ->map(function ($entry): array {
                $card = $entry->card();

                return [
                    'title' => $card->title,
                    'subtitle' => $card->subtitle,
                    'type' => $card->type,
                    'url' => $entry->url(),
                    'occurred_at' => $entry->occurred_at,
                ];
            })
            ->all();
    }
}
