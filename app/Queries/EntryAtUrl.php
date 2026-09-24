<?php

namespace App\Queries;

use App\Datasets\Datasets;
use App\Enums\EntryStatus;
use App\Models\Scopes\ListedScope;
use App\Models\TimelineEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * The entry at a dated URL. Shared by the entry page and its export routes so
 * the lookup, the draft fallback and the misses stay in one place.
 */
final class EntryAtUrl
{
    public function __invoke(int $year, int $month, int $day, string $slug): ?Model
    {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);

        $entry = TimelineEntry::query()->withoutGlobalScope(ListedScope::class)
            ->with('entry')
            ->whereDate('occurred_at', $date)
            ->where('url_slug', $slug)
            ->first();

        $model = $entry?->entry;
        $model?->setRelation('timelineEntry', $entry);

        // Drafts have no spine row, so the owner reaches a dated one directly.
        if ($model === null && Auth::check()) {
            $model = $this->draftAt($date, $slug);
        }

        return $model;
    }

    /** The owner's draft at a dated address; each draftable type is checked by date and slug. */
    private function draftAt(string $date, string $slug): ?Model
    {
        foreach (Datasets::all() as $dataset) {
            if (! $dataset->draftable()) {
                continue;
            }

            $draft = $dataset->model()::query()
                ->where('status', EntryStatus::Draft)
                ->whereDate('occurred_at', $date)
                ->get()
                ->first(fn (Model $model): bool => $model->slug() === $slug);

            if ($draft !== null) {
                return $draft;
            }
        }

        return null;
    }
}
