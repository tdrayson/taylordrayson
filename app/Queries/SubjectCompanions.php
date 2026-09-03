<?php

namespace App\Queries;

use App\Enums\PhotoTagRole;
use App\Models\Attachment;
use App\Models\PhotoTag;
use App\Models\Subject;
use App\Models\Subjectable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The subjects most often appearing (per {@see SubjectFeed::targets()})
 * on the same entries as this one, most-shared first. Issue #82.
 *
 * Camera credits are company to nobody, on either side of the count: a phone
 * is not in the frame it took, and every subject it ever photographed would
 * otherwise read as its companion.
 */
final class SubjectCompanions
{
    private const LIMIT = 8;

    public function __construct(private readonly SubjectFeed $feed) {}

    /** @return Collection<int, Subject> */
    public function __invoke(Subject $subject, int $limit = self::LIMIT): Collection
    {
        if ($this->isCameraOnly($subject)) {
            return collect();
        }

        $targets = $this->feed->targets($subject);

        if ($targets->isEmpty()) {
            return collect();
        }

        $counts = $this->directCoTags($subject, $targets)
            ->merge($this->photoCoTags($subject, $targets))
            ->countBy();

        if ($counts->isEmpty()) {
            return collect();
        }

        $topIds = $counts->sortDesc()->take($limit)->keys();

        return Subject::query()
            ->whereIn('id', $topIds)
            ->get()
            ->sortByDesc(fn (Subject $companion): int => $counts[$companion->id])
            ->values();
    }

    /**
     * Subject ids directly tagged on any of the shared entries.
     *
     * @param  Collection<int, array{type: string, id: int}>  $targets
     * @return Collection<int, int>
     */
    private function directCoTags(Subject $subject, Collection $targets): Collection
    {
        return Subjectable::query()
            ->where('subject_id', '!=', $subject->id)
            ->where(function (Builder $query) use ($targets): void {
                foreach ($targets->groupBy('type') as $type => $group) {
                    $query->orWhere(fn (Builder $q): Builder => $q
                        ->where('subjectable_type', $type)
                        ->whereIn('subjectable_id', $group->pluck('id')));
                }
            })
            ->pluck('subject_id');
    }

    /**
     * Subject ids tagged on a photograph belonging to any of the shared
     * entries.
     *
     * @param  Collection<int, array{type: string, id: int}>  $targets
     * @return Collection<int, int>
     */
    private function photoCoTags(Subject $subject, Collection $targets): Collection
    {
        $attachmentIds = Attachment::query()
            ->where(function (Builder $query) use ($targets): void {
                foreach ($targets->groupBy('type') as $type => $group) {
                    $query->orWhere(fn (Builder $q): Builder => $q
                        ->where('model_type', $type)
                        ->whereIn('model_id', $group->pluck('id')));
                }
            })
            ->pluck('id');

        if ($attachmentIds->isEmpty()) {
            return collect();
        }

        return PhotoTag::query()
            ->where('subject_id', '!=', $subject->id)
            ->where('role', PhotoTagRole::Subject->value)
            ->whereIn('attachment_id', $attachmentIds)
            ->pluck('subject_id');
    }

    /**
     * A subject that only ever took photographs and was never in one, nor
     * tagged on an entry: its "companions" would be its whole photo roll.
     */
    private function isCameraOnly(Subject $subject): bool
    {
        if (Subjectable::query()->where('subject_id', $subject->id)->exists()) {
            return false;
        }

        return ! PhotoTag::query()
            ->where('subject_id', $subject->id)
            ->where('role', PhotoTagRole::Subject->value)
            ->exists();
    }
}
