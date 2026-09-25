<?php

namespace App\Queries;

use App\Enums\PhotoTagRole;
use App\Enums\SubjectKind;
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
        return $this->ranked($subject)->take($limit);
    }

    /**
     * The same ranking split under its headings, in kind order, each capped
     * separately: a spot visited weekly would otherwise crowd out the people
     * who were there.
     *
     * @return Collection<string, Collection<int, Subject>>
     */
    public function grouped(Subject $subject, int $perHeading = self::LIMIT): Collection
    {
        $order = collect(SubjectKind::cases())->map(fn (SubjectKind $kind): string => $kind->companionHeading())->unique()->values();

        return $this->ranked($subject)
            ->groupBy(fn (Subject $companion): string => $companion->kind->companionHeading())
            ->sortBy(fn (Collection $group, string $heading): int => $order->search($heading))
            ->map(fn (Collection $group): Collection => $group->take($perHeading)->values());
    }

    /**
     * Every subject sharing an entry with this one, most-shared first.
     *
     * @return Collection<int, Subject>
     */
    private function ranked(Subject $subject): Collection
    {
        if ($this->isCameraOnly($subject)) {
            return collect();
        }

        $targets = $this->feed->targets($subject);

        if ($targets->isEmpty()) {
            return collect();
        }

        // Each side yields "entryType:entryId:subjectId", so a companion tagged
        // both directly and in three photographs of the same outing counts once
        // for it. The ranking is entries shared, not tag rows written.
        $counts = $this->directCoTags($subject, $targets)
            ->merge($this->photoCoTags($subject, $targets))
            ->unique()
            ->map(fn (string $pair): int => (int) substr($pair, (int) strrpos($pair, ':') + 1))
            ->countBy();

        if ($counts->isEmpty()) {
            return collect();
        }

        return Subject::query()
            ->whereIn('id', $counts->keys())
            ->get()
            ->sortByDesc(fn (Subject $companion): int => $counts[$companion->id])
            ->values();
    }

    /**
     * Entry/subject pairs for the subjects directly tagged on any of the
     * shared entries.
     *
     * @param  Collection<int, array{type: string, id: int}>  $targets
     * @return Collection<int, string>
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
            ->get(['subjectable_type', 'subjectable_id', 'subject_id'])
            ->map(fn (Subjectable $row): string => "{$row->subjectable_type}:{$row->subjectable_id}:{$row->subject_id}")
            ->toBase();
    }

    /**
     * Entry/subject pairs for the subjects tagged in a photograph belonging to
     * any of the shared entries, keyed by the owning entry rather than the
     * photograph so several photographs of one outing count once.
     *
     * @param  Collection<int, array{type: string, id: int}>  $targets
     * @return Collection<int, string>
     */
    private function photoCoTags(Subject $subject, Collection $targets): Collection
    {
        $owners = Attachment::query()
            ->where(function (Builder $query) use ($targets): void {
                foreach ($targets->groupBy('type') as $type => $group) {
                    $query->orWhere(fn (Builder $q): Builder => $q
                        ->where('model_type', $type)
                        ->whereIn('model_id', $group->pluck('id')));
                }
            })
            ->get(['id', 'model_type', 'model_id'])
            ->mapWithKeys(fn (Attachment $attachment): array => [
                $attachment->id => "{$attachment->model_type}:{$attachment->model_id}",
            ]);

        if ($owners->isEmpty()) {
            return collect();
        }

        return PhotoTag::query()
            ->where('subject_id', '!=', $subject->id)
            ->where('role', PhotoTagRole::Subject->value)
            ->whereIn('attachment_id', $owners->keys())
            ->get(['attachment_id', 'subject_id'])
            ->map(fn (PhotoTag $tag): string => "{$owners[$tag->attachment_id]}:{$tag->subject_id}")
            ->toBase();
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
