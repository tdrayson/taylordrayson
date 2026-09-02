<?php

namespace App\Queries;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Subjects an entry's prose already names but has not tagged, for the picker
 * to offer as one-tap suggestions. A mention is a suggestion, never a tag: it
 * is never attached here or anywhere automatically.
 */
final class MentionedSubjects
{
    /**
     * @return Collection<int, Subject>
     */
    public function __invoke(Model $entry): Collection
    {
        $tagged = $entry->subjects->pluck('id');

        $ids = collect($this->references($entry->content ?? []))
            ->reject(fn (int $id): bool => $tagged->contains($id))
            ->unique()
            ->values();

        return Subject::query()->whereIn('id', $ids)->get();
    }

    /**
     * Every `{_type: 'mention', kind: 'subject'}` node's id, callouts included
     * since they nest their own blocks. Mirrors {@see ResolveMentions::references()}.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return list<int>
     */
    private function references(array $blocks): array
    {
        $ids = [];

        foreach ($blocks as $block) {
            if (($block['_type'] ?? null) === 'callout') {
                $ids = [...$ids, ...$this->references($block['children'] ?? [])];

                continue;
            }

            foreach ($block['children'] ?? [] as $child) {
                if (($child['_type'] ?? null) === 'mention' && ($child['kind'] ?? null) === 'subject' && isset($child['id'])) {
                    $ids[] = (int) $child['id'];
                }
            }
        }

        return $ids;
    }
}
