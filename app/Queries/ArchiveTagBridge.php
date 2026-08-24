<?php

namespace App\Queries;

use App\Data\TagLink;
use App\Models\Article;
use App\Models\Tag;
use App\Models\Taggable;
use App\Models\TimelineEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * The "see everything tagged X" bridge from an archive taxonomy page to the /tags
 * union. Null on index pages and whenever the tag would resolve to nothing the
 * requester can see, using the same visibility condition as
 * TagController::show so the link never points at a 404.
 */
final class ArchiveTagBridge
{
    /**
     * @return array{name: string, slug: string, url: string}|null
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

        return TagLink::for($tag)->toArray();
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

        // whereHasMorph joins to the related model, so orphaned spine rows drop out
        // here just as TagController::show discards a null timelineable.
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
