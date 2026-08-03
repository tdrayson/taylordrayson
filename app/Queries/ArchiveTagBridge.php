<?php

namespace App\Queries;

use App\Models\Article;
use App\Models\Tag;
use App\Models\Taggable;
use App\Models\TimelineEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * The "see everything tagged X" bridge for an archive taxonomy page. Given the
 * taxonomy value in the URL, returns the cross-type tag whose slug matches it,
 * so a single-type archive slice (e.g. musical events, notes tagged coffee) can
 * link to the /tags union that spans every taggable type.
 *
 * Returns null on index pages (no value) and whenever no matching tag resolves
 * to an entry visible to the current requester, so the link never points at a
 * /tags feed that would 404. Visibility is decided by the same condition
 * TagController::show uses for its 200/404 boundary, so the two stay in lockstep.
 */
final class ArchiveTagBridge
{
    /**
     * @return array{name: string, slug: string}|null
     */
    public function __invoke(?string $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $tag = Tag::query()->where('slug', $value)->first(['id', 'name', 'slug']);

        if ($tag === null) {
            return null;
        }

        $taggables = Taggable::query()
            ->where('tag_id', $tag->id)
            ->get(['taggable_type', 'taggable_id']);

        if ($taggables->isEmpty() || ! $this->resolvesForRequester($taggables)) {
            return null;
        }

        return ['name' => $tag->name, 'slug' => $tag->slug];
    }

    /**
     * Whether this tag's members resolve to at least one entry the current
     * requester can see. Mirrors TagController::show's own 200/404 condition:
     * a spine (TimelineEntry) row for any tagged model, plus the authenticated
     * owner's preview of unpublished tagged articles (which have no spine row of
     * their own). Kept in lockstep with the tag feed so the bridge never links
     * to a page that would 404 for the same requester.
     *
     * @param  Collection<int, Taggable>  $taggables
     */
    private function resolvesForRequester(Collection $taggables): bool
    {
        $types = $taggables->pluck('taggable_type')->unique()->values()->all();

        // whereHasMorph joins to each related model, so an orphaned spine row (a
        // model deleted by a bulk delete that skipped the observer, leaving its
        // entry behind) is excluded here just as TagController::show discards
        // entries whose timelineable resolves to null.
        $hasSpineEntry = TimelineEntry::query()
            ->whereHasMorph('timelineable', $types, function (Builder $query, string $type) use ($taggables): void {
                $query->whereKey($taggables->where('taggable_type', $type)->pluck('taggable_id'));
            })
            ->exists();

        if ($hasSpineEntry) {
            return true;
        }

        if (! Auth::check()) {
            return false;
        }

        return Article::query()
            ->whereIn('id', $taggables->where('taggable_type', Article::class)->pluck('taggable_id'))
            ->where('published', false)
            ->exists();
    }
}
