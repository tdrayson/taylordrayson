<?php

namespace App\Queries;

use App\Data\TagLink;
use App\Enums\EntryStatus;
use App\Models\Tag;
use App\Models\Taggable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * Every tag attached to at least one listed entry, with how many listed entries
 * carry it. The owner sees the same counts as a guest.
 */
final class TagUsage
{
    /**
     * @return Collection<int, array{name: string, slug: string, url: string, count: int}>
     */
    public function __invoke(): Collection
    {
        $counts = Taggable::query()
            ->whereExists(fn (Builder $sub) => $sub->selectRaw('1')
                ->from('timeline_entries')
                ->whereColumn('timeline_entries.dataset', 'taggables.taggable_type')
                ->whereColumn('timeline_entries.entry_id', 'taggables.taggable_id')
                ->where('timeline_entries.status', EntryStatus::Published->value))
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
