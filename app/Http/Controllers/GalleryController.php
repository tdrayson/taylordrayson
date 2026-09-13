<?php

namespace App\Http\Controllers;

use App\Enums\PhotoFilter;
use App\Queries\PhotoStream;
use App\Support\OgMeta;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class GalleryController extends Controller
{
    /**
     * Photos shaped per request. Rather more than a screenful on a wide
     * display, so the grid is still filling as the next page is fetched.
     */
    private const PER_PAGE = 60;

    public function __construct(private readonly PhotoStream $photos) {}

    /**
     * The photo gallery: every real photo across every timeline entry, newest
     * first, optionally narrowed to a work-queue facet. The filtering and
     * ordering live in PhotoStream so this page and the "Life lately" widget
     * on /now stay in lockstep. Signed out, the facet resolves to null and the
     * counts are never computed, so the page renders exactly as it did before.
     *
     * A page at a time, deferred, and appended as the visitor scrolls: shaping
     * all of them took seconds, and nothing could paint until it finished.
     */
    public function index(Request $request): Response
    {
        $signedIn = Auth::check();
        $filter = $signedIn ? PhotoFilter::tryFrom((string) $request->query('filter')) : null;
        $photos = $this->photos->filtered($filter);

        return Inertia::render('Photos', [
            'og' => OgMeta::gallery(),
            'total' => $photos->count(),
            'photos' => Inertia::scroll(
                fn () => $photos->paginate(self::PER_PAGE, Paginator::resolveCurrentPage()),
            )->defer(),
            'filter' => $filter?->value,
            'filters' => $signedIn ? $this->filters() : null,
        ]);
    }

    /**
     * Facet counts for the filter bar, counted in the database rather than by
     * shaping a stream per facet.
     *
     * @return array<int, array{value: string|null, label: string, count: int}>
     */
    private function filters(): array
    {
        return [
            ['value' => null, 'label' => 'Everything', 'count' => $this->photos->count()],
            ...collect(PhotoFilter::cases())->map(fn (PhotoFilter $filter): array => [
                'value' => $filter->value,
                'label' => $filter->label(),
                'count' => $this->photos->filtered($filter)->count(),
            ])->all(),
        ];
    }
}
