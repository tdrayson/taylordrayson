<?php

namespace App\Http\Controllers;

use App\Enums\PhotoFilter;
use App\Models\Attachment;
use App\Queries\PhotoStream;
use App\Support\GalleryPhotos;
use App\Support\OgMeta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class GalleryController extends Controller
{
    public function __construct(private readonly PhotoStream $photos) {}

    /**
     * The photo gallery: every real photo across every timeline entry, newest
     * first, optionally narrowed to a work-queue facet. The filtering and
     * ordering live in PhotoStream so this page and the "Life lately" widget
     * on /now stay in lockstep. Signed out, the facet resolves to null and the
     * counts are never computed, so the page renders exactly as it did before.
     */
    public function index(Request $request): Response
    {
        $signedIn = Auth::check();
        $filter = $signedIn ? PhotoFilter::tryFrom((string) $request->query('filter')) : null;

        return Inertia::render('Photos', [
            'og' => OgMeta::gallery(),
            'photos' => ($this->photos)(null, $filter?->value),
            'filter' => $filter?->value,
            'filters' => $signedIn ? $this->filters() : null,
        ]);
    }

    /**
     * Facet counts for the filter bar. A count query per facet, not a run of
     * PhotoStream per facet: PhotoStream shapes card presentation and URLs for
     * every photo it returns, which only the photos actually shown need to pay
     * for.
     *
     * @return array<int, array{value: string|null, label: string, count: int}>
     */
    private function filters(): array
    {
        $modelTypes = GalleryPhotos::contributingModelTypes();
        $base = fn () => Attachment::query()
            ->whereIn('collection_name', ['cover', 'photos'])
            ->whereIn('model_type', $modelTypes);

        return [
            ['value' => null, 'label' => 'Everything', 'count' => $base()->count()],
            ...collect(PhotoFilter::cases())->map(fn (PhotoFilter $filter): array => [
                'value' => $filter->value,
                'label' => $filter->label(),
                'count' => $filter->apply($base())->count(),
            ])->all(),
        ];
    }
}
