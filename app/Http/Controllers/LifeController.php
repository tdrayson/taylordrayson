<?php

namespace App\Http\Controllers;

use App\Enums\SubjectCategory;
use App\Enums\SubjectKind;
use App\Models\Subject;
use App\Queries\SubjectCounts;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        ]);
    }

    /**
     * A kind index: every subject of this kind, optionally narrowed to a
     * category. An unknown kind 404s; an unknown or mismatched category is
     * ignored rather than erroring, since the facet is a filter, not identity.
     */
    public function kind(string $kind, Request $request): Response
    {
        $subjectKind = SubjectKind::fromSegment($kind);

        abort_if($subjectKind === null, 404);

        $category = SubjectCategory::tryFrom((string) $request->query('category'));

        if ($category !== null && $category->kind() !== $subjectKind) {
            $category = null;
        }

        $subjects = Subject::query()
            ->where('kind', $subjectKind)
            ->when($category, fn ($query) => $query->where('category', $category))
            ->orderBy('name')
            ->get();

        return Inertia::render('Life/Kind', [
            'kind' => $subjectKind->plural(),
            'kindLabel' => $subjectKind->label(),
            'segment' => $subjectKind->segment(),
            'categories' => collect(SubjectCategory::forKind($subjectKind))
                ->map(fn (SubjectCategory $option): array => ['value' => $option->value, 'label' => $option->label()])
                ->all(),
            'category' => $category?->value,
            // Off the kind's whole population, not the filtered $subjects: a
            // category with zero current rows must not take the facet bar
            // (including "All") down with it.
            'hasCategories' => Subject::query()->where('kind', $subjectKind)->whereNotNull('category')->exists(),
            'subjects' => $subjects->map(fn (Subject $subject): array => [
                'name' => $subject->name,
                'slug' => $subject->slug,
                'url' => $subject->url(),
                'cover' => $subject->coverPhoto(),
                'category' => $subject->category?->label(),
            ])->all(),
        ]);
    }

    /**
     * @param  Collection<int, Subject>  $subjects
     * @return array<int, array<string, mixed>>
     */
    private function recent(Collection $subjects): array
    {
        return $subjects->take(4)->map(fn (Subject $subject): array => [
            'name' => $subject->name,
            'url' => $subject->url(),
            'cover' => $subject->coverPhoto(),
        ])->all();
    }
}
