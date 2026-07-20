<?php

namespace App\Http\Controllers;

use App\Search\FilterValidator;
use App\Search\RunSearch;
use App\Search\SearchPresets;
use App\Search\SearchSchema;
use App\Search\SuggestSearch;
use App\Support\OgMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    /**
     * @param  FilterValidator  $filterValidator  Decodes and whitelists the URL-encoded filter against the schema.
     * @param  RunSearch  $runSearch  Runs a validated filter and paginates the feed results.
     * @param  SuggestSearch  $suggestSearch  Runs the command palette's free-text + destination search.
     */
    public function __construct(
        private readonly FilterValidator $filterValidator,
        private readonly RunSearch $runSearch,
        private readonly SuggestSearch $suggestSearch,
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
        $groups = ($this->filterValidator)($request->input('filter'));
        $page = max(1, (int) $request->input('page', 1));
        $order = $request->input('order') === 'oldest' ? 'oldest' : 'newest';
        $results = ($this->runSearch)($groups, $page, $order);

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

        return response()->json(($this->suggestSearch)($term));
    }
}
