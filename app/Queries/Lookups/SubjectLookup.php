<?php

namespace App\Queries\Lookups;

use App\Enums\SubjectKind;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;

/**
 * Existing subjects, so the same person, pet, spot or thing does not end up
 * filed under three separate rows. Ranked like {@see TagLookup}: how much a
 * subject is already tagged, then name.
 */
final class SubjectLookup
{
    /**
     * `Self` is excluded by default; a caller taggable with Self passes `includeSelf: true`.
     * `$kind` narrows the list to one sort of subject, for a picker that can
     * only accept one (the camera credit is always a thing).
     *
     * @return list<array{value: int, label: string, detail: string}>
     */
    public function __invoke(string $query, bool $includeSelf = false, ?SubjectKind $kind = null): array
    {
        $counts = DB::table('subjectables')
            ->selectRaw('subject_id, count(*) as total')
            ->groupBy('subject_id')
            ->pluck('total', 'subject_id');

        return Subject::query()
            ->when(! $includeSelf, fn ($builder) => $builder->where('slug', '!=', config('life.self_slug')))
            ->when($kind !== null, fn ($builder) => $builder->where('kind', $kind))
            ->when($query !== '', fn ($builder) => $builder->where('name', 'like', "%{$query}%"))
            ->get()
            ->sortBy([
                fn (Subject $a, Subject $b): int => ($counts[$b->id] ?? 0) <=> ($counts[$a->id] ?? 0),
                fn (Subject $a, Subject $b): int => $a->name <=> $b->name,
            ])
            ->take(10)
            ->map(fn (Subject $subject): array => [
                'value' => $subject->id,
                'label' => $subject->name,
                'detail' => $subject->kind->label(),
            ])
            ->values()
            ->all();
    }
}
