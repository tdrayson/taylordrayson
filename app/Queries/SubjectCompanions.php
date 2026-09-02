<?php

namespace App\Queries;

use App\Models\Subject;
use App\Models\Subjectable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The subjects most often directly tagged on the same entries as this one,
 * most-shared first, excluding the subject itself. Issue #82's "people I ride
 * with": a Strava companion is tagged directly on the ride, not through a
 * photograph, so this counts direct co-tagging only.
 */
final class SubjectCompanions
{
    private const LIMIT = 8;

    /** @return Collection<int, Subject> */
    public function __invoke(Subject $subject, int $limit = self::LIMIT): Collection
    {
        $targets = Subjectable::query()
            ->where('subject_id', $subject->id)
            ->get(['subjectable_type', 'subjectable_id']);

        if ($targets->isEmpty()) {
            return collect();
        }

        $counts = Subjectable::query()
            ->where('subject_id', '!=', $subject->id)
            ->where(function (Builder $query) use ($targets): void {
                foreach ($targets->groupBy('subjectable_type') as $type => $group) {
                    $query->orWhere(fn (Builder $q): Builder => $q
                        ->where('subjectable_type', $type)
                        ->whereIn('subjectable_id', $group->pluck('subjectable_id')));
                }
            })
            ->selectRaw('subject_id, count(*) as total')
            ->groupBy('subject_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->pluck('total', 'subject_id');

        if ($counts->isEmpty()) {
            return collect();
        }

        return Subject::query()
            ->whereIn('id', $counts->keys())
            ->get()
            ->sortByDesc(fn (Subject $companion): int => $counts[$companion->id])
            ->values();
    }
}
