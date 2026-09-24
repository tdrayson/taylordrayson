<?php

namespace App\Queries;

use App\Data\TagLink;
use App\Models\Tag;
use App\Models\Taggable;
use App\Models\TimelineEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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
     * Whether this tag's members resolve to at least one listed entry, the same
     * condition TagController::show 404s on.
     *
     * @param  Collection<int, Taggable>  $taggables
     */
    private function resolvesForRequester(Collection $taggables): bool
    {
        $types = $taggables->pluck('taggable_type')->unique()->values()->all();

        // whereHasMorph joins to the related model, so orphaned spine rows drop out
        // here just as TagController::show discards a null entry.
        return TimelineEntry::query()
            ->whereHasMorph('entry', $types, function (Builder $query, string $type) use ($taggables): void {
                // whereHasMorph resolves $types back to real classes, but taggable_type
                // stores the alias, so it has to be translated back to match.
                $query->whereKey($taggables->where('taggable_type', (new $type)->getMorphClass())->pluck('taggable_id'));
            })
            ->exists();
    }
}
