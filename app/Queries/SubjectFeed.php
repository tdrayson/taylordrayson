<?php

namespace App\Queries;

use App\Actions\BuildTimelineFeed;
use App\Http\Controllers\TagController;
use App\Models\Article;
use App\Models\Attachment;
use App\Models\Concerns\HasSubjects;
use App\Models\Subject;
use App\Models\Subjectable;
use App\Models\TimelineEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Cross-type feed of every timeline entry a subject is on, resolved through
 * the spine the same way {@see TagController} does.
 * A subject's own direct tags and the owners of its tagged photographs are
 * unioned before resolving, so an entry reached only through a photograph
 * still appears, matching {@see HasSubjects::allSubjects()}.
 */
final class SubjectFeed
{
    public function __construct(private readonly BuildTimelineFeed $feed) {}

    /** @return array<int, array<string, mixed>> */
    public function __invoke(Subject $subject): array
    {
        $targets = $this->targets($subject);

        if ($targets->isEmpty()) {
            return [];
        }

        $entries = TimelineEntry::query()
            ->withCardRelations()
            ->where(function (Builder $query) use ($targets): void {
                foreach ($targets->groupBy('type') as $type => $group) {
                    $query->orWhere(fn (Builder $q): Builder => $q
                        ->where('timelineable_type', $type)
                        ->whereIn('timelineable_id', $group->pluck('id')));
                }
            })
            ->get()
            ->filter(fn (TimelineEntry $entry): bool => $entry->timelineable !== null);

        if (Auth::check()) {
            $entries = $entries->concat($this->unpublishedArticlePreviews($targets));
        }

        return $this->feed->groupByDay(
            $entries->sortByDesc(fn (TimelineEntry $entry): int => $entry->occurred_at->getTimestamp())->values()
        );
    }

    /**
     * The union of the subject's own subjectable rows and the owning models of
     * every attachment it is tagged on. Public so other queries (e.g.
     * {@see SubjectCompanions}) can build on the same definition of "which
     * entries is this subject on" rather than a second, divergent one.
     *
     * @return Collection<int, array{type: string, id: int}>
     */
    public function targets(Subject $subject): Collection
    {
        // toBase(): Eloquent\Collection::map() only downgrades to a plain
        // Collection when it can see a non-Model item in the *result*, and an
        // empty result has none to see, so a subject with zero direct tags
        // (photo-only, the common case) leaves $direct Eloquent-typed. merge()
        // then picks Eloquent's Model-keyed implementation and throws on the
        // plain arrays here.
        $direct = Subjectable::query()
            ->where('subject_id', $subject->id)
            ->get(['subjectable_type', 'subjectable_id'])
            ->map(fn (Subjectable $row): array => ['type' => $row->subjectable_type, 'id' => $row->subjectable_id])
            ->toBase();

        $viaPhotos = $subject->attachments()
            ->get(['attachments.model_type', 'attachments.model_id'])
            ->map(fn (Attachment $attachment): array => ['type' => $attachment->model_type, 'id' => $attachment->model_id])
            ->toBase();

        return $direct->merge($viaPhotos)
            ->unique(fn (array $target): string => "{$target['type']}:{$target['id']}")
            ->values();
    }

    /**
     * Build transient (unsaved) timeline entries for unpublished, subject-tagged
     * articles so an authenticated preview sees them despite there being no
     * spine row to resolve through, mirroring TagController's own fallback.
     *
     * @param  Collection<int, array{type: string, id: int}>  $targets
     * @return Collection<int, TimelineEntry>
     */
    private function unpublishedArticlePreviews(Collection $targets): Collection
    {
        $articleIds = $targets->where('type', Article::class)->pluck('id');

        if ($articleIds->isEmpty()) {
            return collect();
        }

        return Article::query()
            ->whereIn('id', $articleIds)
            ->where('published', false)
            ->get()
            ->map(function (Article $article): TimelineEntry {
                $entry = new TimelineEntry(['occurred_at' => $article->occurred_at]);
                $entry->setRelation('timelineable', $article);

                return $entry;
            });
    }
}
