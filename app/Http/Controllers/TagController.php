<?php

namespace App\Http\Controllers;

use App\Actions\BuildTimelineFeed;
use App\Models\Article;
use App\Models\Tag;
use App\Models\Taggable;
use App\Models\TimelineEntry;
use App\Queries\TagUsage;
use App\Support\OgMeta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function __construct(private readonly BuildTimelineFeed $feed) {}

    /**
     * The tag index: every visible tag with its usage count, for the weighted
     * cloud and the A-Z list.
     */
    public function index(TagUsage $tags): Response
    {
        return Inertia::render('Tags', [
            'og' => OgMeta::tags(),
            'tags' => $tags(),
        ]);
    }

    /**
     * Cross-type tag feed: every article, note, and project carrying this tag,
     * shaped identically to the timeline. 404s for an unknown slug, and also
     * when nothing resolves for the current requester (e.g. a tag that only
     * lives on an unpublished article, viewed by a guest) so a probing URL
     * cannot distinguish "no such tag" from "nothing visible to you".
     */
    public function show(string $slug): Response
    {
        $tag = Tag::query()->where('slug', $slug)->first();

        abort_if($tag === null, 404);

        $entries = $this->resolveEntries($tag);

        abort_if($entries->isEmpty(), 404);

        return Inertia::render('Tag', [
            'og' => OgMeta::tag($tag->name, $entries->count()),
            'name' => $tag->name,
            'groups' => $this->feed->groupByDay($entries),
        ]);
    }

    /**
     * Resolve every timeline entry for models carrying this tag, through the
     * spine so unpublished articles are naturally hidden from guests (they
     * have no spine row for anyone to resolve through). An authenticated
     * request additionally previews unpublished tagged articles directly,
     * mirroring EntryController's own-preview fallback.
     *
     * @return Collection<int, TimelineEntry>
     */
    private function resolveEntries(Tag $tag): Collection
    {
        $taggables = Taggable::query()->where('tag_id', $tag->id)->get(['taggable_type', 'taggable_id']);

        if ($taggables->isEmpty()) {
            return collect();
        }

        $entries = TimelineEntry::query()
            ->withCardRelations()
            ->where(function (Builder $query) use ($taggables): void {
                foreach ($taggables->groupBy('taggable_type') as $type => $group) {
                    $query->orWhere(fn (Builder $q): Builder => $q
                        ->where('timelineable_type', $type)
                        ->whereIn('timelineable_id', $group->pluck('taggable_id')));
                }
            })
            ->get()
            ->filter(fn (TimelineEntry $entry): bool => $entry->timelineable !== null);

        if (Auth::check()) {
            $entries = $entries->concat($this->unpublishedArticlePreviews($taggables));
        }

        return $entries->sortByDesc(fn (TimelineEntry $entry): int => $entry->occurred_at->getTimestamp())->values();
    }

    /**
     * Build transient (unsaved) timeline entries for unpublished, tagged
     * articles so an authenticated preview sees them despite there being no
     * spine row to resolve through.
     *
     * @param  Collection<int, Taggable>  $taggables
     * @return Collection<int, TimelineEntry>
     */
    private function unpublishedArticlePreviews(Collection $taggables): Collection
    {
        $articleIds = $taggables->where('taggable_type', Article::class)->pluck('taggable_id');

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
