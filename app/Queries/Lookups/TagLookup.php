<?php

namespace App\Queries\Lookups;

use App\Models\Tag;
use App\Models\Taggable;
use App\Queries\TagUsage;

/**
 * Existing tags, so the same idea does not end up filed under three spellings.
 *
 * Ordered by how much a tag is already used: an established tag is far more
 * likely to be the one meant than something typed once last year. No publish
 * gate, unlike {@see TagUsage}, because only the author sees this
 * and a draft's tags are exactly the ones being reused.
 */
final class TagLookup
{
    /**
     * @return list<array{value: string, label: string, detail: string|null}>
     */
    public function __invoke(string $query): array
    {
        $counts = Taggable::query()
            ->selectRaw('tag_id, count(*) as total')
            ->groupBy('tag_id')
            ->pluck('total', 'tag_id');

        return Tag::query()
            ->when($query !== '', fn ($builder) => $builder->where('name', 'like', "%{$query}%"))
            ->get()
            ->sortByDesc(fn (Tag $tag): int => (int) ($counts[$tag->id] ?? 0))
            ->take(10)
            ->map(fn (Tag $tag): array => [
                'value' => $tag->name,
                'label' => $tag->name,
                'detail' => ($counts[$tag->id] ?? 0) > 0 ? (string) $counts[$tag->id] : null,
            ])
            ->values()
            ->all();
    }
}
