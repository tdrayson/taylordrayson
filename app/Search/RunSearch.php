<?php

namespace App\Search;

use App\Actions\BuildTimelineFeed;
use App\Models\Scopes\ListedScope;
use App\Models\TimelineEntry;
use App\Support\FeedInteractions;

/**
 * Runs a validated advanced-search filter (as produced by FilterValidator)
 * and shapes the requested page of matching entries for the feed.
 */
final class RunSearch
{
    private const PER_PAGE = 25;

    /**
     * @param  BuildTimelineFeed  $feed  Shapes entries into feed-card day groups.
     * @param  SearchCompiler  $compiler  Compiles a filter into a TimelineEntry query.
     */
    public function __construct(
        private readonly BuildTimelineFeed $feed,
        private readonly SearchCompiler $compiler,
    ) {}

    /**
     * Run the compiled filter and shape the requested page of results for the feed.
     *
     * @param  array<int, array<string, mixed>>  $groups  Validated filter groups.
     * @param  int  $page  The 1-based results page to load.
     * @param  string  $order  'newest' (default) or 'oldest', by occurrence.
     * @return array{groups: array<int, mixed>, interactions: mixed, total: int, currentPage: int, lastPage: int}
     */
    public function __invoke(array $groups, int $page, string $order = 'newest'): array
    {
        if ($groups === []) {
            return ['groups' => [], 'interactions' => [], 'total' => 0, 'currentPage' => 1, 'lastPage' => 1];
        }

        $query = TimelineEntry::query()->withoutGlobalScope(ListedScope::class)
            ->withCardRelations();

        $this->compiler->apply($query, $groups);

        $paginated = $query
            ->orderBy('occurred_at', $order === 'oldest' ? 'asc' : 'desc')
            ->paginate(self::PER_PAGE, ['*'], 'page', $page);

        $entries = collect($paginated->items());

        return [
            'groups' => $this->feed->groupByDay($entries),
            'interactions' => FeedInteractions::defer($entries),
            'total' => $paginated->total(),
            'currentPage' => $paginated->currentPage(),
            'lastPage' => $paginated->lastPage(),
        ];
    }
}
