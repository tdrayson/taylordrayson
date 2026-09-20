<?php

namespace App\Http\Controllers;

use App\Actions\BuildTimelineFeed;
use App\Models\Tag;
use App\Models\Taggable;
use App\Models\TimelineEntry;
use App\Queries\TagUsage;
use App\Support\FeedInteractions;
use App\Support\OgMeta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
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
     * when nothing resolves for the current requester (a tag that only lives
     * on unlisted entries) so a probing URL cannot distinguish "no such tag"
     * from "nothing visible to you".
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
            'interactions' => FeedInteractions::defer($entries),
        ]);
    }

    /**
     * Every listed timeline entry for models carrying this tag.
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
                        ->where('dataset', $type)
                        ->whereIn('entry_id', $group->pluck('taggable_id')));
                }
            })
            ->get()
            ->filter(fn (TimelineEntry $entry): bool => $entry->entry !== null);

        return $entries->sortByDesc(fn (TimelineEntry $entry): int => $entry->occurred_at->getTimestamp())->values();
    }
}
