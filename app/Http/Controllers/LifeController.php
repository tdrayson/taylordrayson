<?php

namespace App\Http\Controllers;

use App\Enums\PhotoTagRole;
use App\Enums\SubjectCategory;
use App\Enums\SubjectKind;
use App\Models\Subject;
use App\Queries\SubjectCounts;
use App\Support\OgMeta;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LifeController extends Controller
{
    /**
     * The life hub: the four kinds, their counts, and a few recent subjects
     * with a cover photo from each.
     */
    public function index(SubjectCounts $counts): Response
    {
        $recentByKind = Subject::query()
            ->latest('updated_at')
            ->get()
            ->groupBy(fn (Subject $subject): string => $subject->kind->segment());

        $totals = $counts();

        return Inertia::render('Life/Index', [
            'kinds' => collect(SubjectKind::cases())->map(fn (SubjectKind $kind): array => [
                'label' => $kind->plural(),
                'segment' => $kind->segment(),
                'count' => $totals[$kind->segment()] ?? 0,
                'recent' => $this->recent($recentByKind->get($kind->segment(), collect())),
            ])->all(),
            'og' => OgMeta::life(),
        ]);
    }

    /**
     * A kind index: every subject of this kind, optionally narrowed to a
     * category. An unknown kind 404s; an unknown category is ignored, not erroring.
     */
    public function kind(string $kind, Request $request): Response
    {
        $subjectKind = SubjectKind::fromSegment($kind);

        abort_if($subjectKind === null, 404);

        // Facets come from what is actually filed under this kind, not from
        // the enum: the category list is open, so a typed-in one has to appear.
        $used = Subject::query()
            ->where('kind', $subjectKind)
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $category = SubjectCategory::normalise($request->query('category'));

        if ($category !== null && ! $used->contains($category)) {
            $category = null;
        }

        $subjects = Subject::query()
            ->where('kind', $subjectKind)
            ->when($category, fn ($query) => $query->where('category', $category))
            ->orderBy('name')
            ->get();

        $weights = $this->weights($subjects->pluck('id'));

        $total = Subject::query()->where('kind', $subjectKind)->count();

        return Inertia::render('Life/Kind', [
            'kind' => $subjectKind->plural(),
            'kindLabel' => $subjectKind->label(),
            'segment' => $subjectKind->segment(),
            'categories' => $used
                ->map(fn (string $value): array => ['value' => $value, 'label' => SubjectCategory::labelFor($value)])
                ->all(),
            'category' => $category,
            'hasCategories' => $used->isNotEmpty(),
            'subjects' => $subjects->map(fn (Subject $subject): array => [
                'name' => $subject->name,
                'slug' => $subject->slug,
                'url' => $subject->url(),
                'cover' => $subject->coverPhoto(),
                'category' => SubjectCategory::labelFor($subject->category),
                // How much of the site a subject occupies, for the grid to
                // size its tile by. A tag count, not a distinct-entry count:
                // the grid only needs the ordering, not the exact figure.
                'weight' => $weights[$subject->id] ?? 0,
            ])->all(),
            'og' => OgMeta::lifeKind($subjectKind, $total),
        ]);
    }

    /**
     * @param  Collection<int, Subject>  $subjects
     * @return array<int, array<string, mixed>>
     */
    /**
     * Tag counts per subject, direct and through a photograph, in two grouped
     * queries rather than one per row.
     *
     * @param  Collection<int, int>  $ids
     * @return Collection<int, int>
     */
    private function weights(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        $direct = DB::table('subjectables')
            ->whereIn('subject_id', $ids)
            ->selectRaw('subject_id, count(*) as total')
            ->groupBy('subject_id')
            ->pluck('total', 'subject_id');

        $viaPhotos = DB::table('attachment_subject')
            ->whereIn('subject_id', $ids)
            ->where('role', PhotoTagRole::Subject->value)
            ->selectRaw('subject_id, count(*) as total')
            ->groupBy('subject_id')
            ->pluck('total', 'subject_id');

        return $ids->mapWithKeys(fn (int $id): array => [
            $id => (int) ($direct[$id] ?? 0) + (int) ($viaPhotos[$id] ?? 0),
        ]);
    }

    private function recent(Collection $subjects): array
    {
        return $subjects->take(8)->map(fn (Subject $subject): array => [
            'name' => $subject->name,
            'url' => $subject->url(),
            'cover' => $subject->coverPhoto(),
        ])->all();
    }
}
