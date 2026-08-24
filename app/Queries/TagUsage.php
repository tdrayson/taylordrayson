<?php

namespace App\Queries;

use App\Data\TagLink;
use App\Models\Article;
use App\Models\Tag;
use App\Models\Taggable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Every tag that is attached to at least one visible entry, with how many
 * entries carry it. Powers the /tags index. Articles are the only publish-gated
 * type, so a guest never sees (or counts) a tag that lives only on unpublished
 * articles; the owner does, mirroring the archive tag chips.
 */
final class TagUsage
{
    /**
     * @return Collection<int, array{name: string, slug: string, url: string, count: int}>
     */
    public function __invoke(): Collection
    {
        $counts = Taggable::query()
            ->when(! Auth::check(), fn ($query) => $query->where(function ($inner): void {
                // Keep every non-article pivot, plus article pivots whose article
                // is published; drop the draft-only article pivots.
                $inner->where('taggable_type', '!=', Article::class)
                    ->orWhereExists(fn (Builder $sub) => $sub->selectRaw('1')
                        ->from('articles')
                        ->whereColumn('articles.id', 'taggables.taggable_id')
                        ->where('articles.published', true));
            }))
            ->selectRaw('tag_id, count(*) as total')
            ->groupBy('tag_id')
            ->pluck('total', 'tag_id');

        if ($counts->isEmpty()) {
            return collect();
        }

        return Tag::query()
            ->whereIn('id', $counts->keys())
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (Tag $tag): array => [
                ...TagLink::for($tag)->toArray(),
                'count' => (int) $counts[$tag->id],
            ]);
    }
}
