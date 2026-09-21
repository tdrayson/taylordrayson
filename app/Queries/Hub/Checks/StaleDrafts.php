<?php

namespace App\Queries\Hub\Checks;

use App\Data\Hub\AttentionItem;
use App\Datasets\Datasets;
use App\Enums\EntryStatus;
use App\Models\Book;
use Illuminate\Support\Carbon;

/**
 * Drafts still warm enough to be worth finishing. Clears when they publish, or
 * when they age past the window on their own.
 */
final class StaleDrafts implements Check
{
    /** Older than this and it was abandoned, not forgotten. */
    private const DAYS = 30;

    /**
     * @return list<AttentionItem>
     */
    public function items(): array
    {
        $since = Carbon::now()->subDays(self::DAYS);
        $counted = 0;
        $newest = null;

        foreach (Datasets::all() as $dataset) {
            if (! $dataset->draftable() || $dataset->model() === Book::class) {
                continue;
            }

            $query = $dataset->model()::query()
                ->where('status', EntryStatus::Draft)
                ->where('updated_at', '>=', $since);

            $count = $query->count();

            if ($count === 0) {
                continue;
            }

            $counted += $count;
            $latest = Carbon::parse($query->max('updated_at'));

            if ($newest === null || $latest->greaterThan($newest)) {
                $newest = $latest;
            }
        }

        if ($counted === 0) {
            return [];
        }

        return [new AttentionItem(
            id: 'stale-drafts',
            kind: 'draft',
            icon: 'File02Icon',
            title: $counted === 1 ? 'Something is still in draft' : "{$counted} things are still in draft",
            detail: 'Edited in the last month.',
            body: null,
            age: $newest->diffForHumans(),
            href: '/drafts',
        )];
    }
}
