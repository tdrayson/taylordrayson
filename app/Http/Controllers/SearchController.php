<?php

namespace App\Http\Controllers;

use App\Actions\BuildTimelineFeed;
use App\Models\Flight;
use App\Models\TimelineEntry;
use App\Search\SearchCompiler;
use App\Search\SearchSchema;
use App\Timeline\TypeRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    private const LIMIT = 8;

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
        $results = $this->runSearch($groups, $page);

        return Inertia::render('Search', [
            'schema' => SearchSchema::forClient(),
            'filter' => $groups,
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
     * @return array{groups: array<int, mixed>, total: int, currentPage: int, lastPage: int}
     */
    private function runSearch(array $groups, int $page): array
    {
        if ($groups === []) {
            return ['groups' => [], 'total' => 0, 'currentPage' => 1, 'lastPage' => 1];
        }

        $query = TimelineEntry::query()
            ->with(['timelineable' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                Flight::class => ['origin', 'destination', 'airline'],
            ])]);

        $this->compiler->apply($query, $groups);

        $paginated = $query->orderByDesc('occurred_at')->paginate(self::PER_PAGE, ['*'], 'page', $page);

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
     * Free-text search across timeline entries for the command palette, optionally
     * narrowed to a single type and/or a date range, ordered by recency.
     *
     * @param  Request  $request  Carries `q`, and optional `type`, `from`, `to`.
     * @return JsonResponse The matching entries as { results: [...] }.
     */
    public function suggest(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        $typeParam = $request->query('type');
        $from = $request->query('from');
        $to = $request->query('to');

        $registry = TypeRegistry::all();
        $type = is_string($typeParam) && isset($registry[$typeParam]) ? $typeParam : null;
        $hasText = mb_strlen($term) >= 2;
        $hasRange = is_string($from) && is_string($to);

        if (! $hasText && ! $hasRange && $type === null) {
            return response()->json(['results' => []]);
        }

        $targets = $type !== null ? [$type] : array_keys(self::SEARCHABLE);

        $results = collect($targets)
            ->flatMap(fn (string $key): array => $this->searchType(
                $registry[$key]['model'],
                $key,
                self::SEARCHABLE[$key] ?? [],
                $hasText ? $term : '',
                $hasRange ? [$from, $to] : null,
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

        return response()->json(['results' => $results]);
    }

    /**
     * Match a single type's text columns (and optional date range) and shape each
     * hit for the palette.
     *
     * @param  class-string  $model
     * @param  array<int, string>  $columns
     * @param  array{0: string, 1: string}|null  $range
     * @return array<int, array{title: string, subtitle: ?string, type: string, url: string, occurred_at: Carbon}>
     */
    private function searchType(string $model, string $type, array $columns, string $term, ?array $range): array
    {
        if ($term !== '' && $columns === []) {
            return [];
        }

        $query = $model::query();

        if ($term !== '' && $columns !== []) {
            $query->where(function (Builder $builder) use ($columns, $term): void {
                foreach ($columns as $column) {
                    $builder->orWhere($column, 'like', "%{$term}%");
                }
            });
        }

        if ($range !== null) {
            $query->whereBetween('occurred_at', $range);
        }

        $query->orderByDesc('occurred_at')->limit(self::PER_TYPE);

        if ($type === 'flight') {
            $query->with(['origin', 'destination', 'airline']);
        }

        return $query->get()
            ->map(function ($entry): array {
                $card = $entry->card();

                return [
                    'title' => $card['title'],
                    'subtitle' => $card['subtitle'] ?? null,
                    'type' => $card['type'],
                    'url' => $entry->url(),
                    'occurred_at' => $entry->occurred_at,
                ];
            })
            ->all();
    }
}
