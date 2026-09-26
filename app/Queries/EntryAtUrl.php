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

    /**
     * The entry behind a path or full URL: a dated `/YYYY/MM/DD/slug` or a draft's `/drafts/{dataset}/{id}`.
     *
     * @param  string  $path  The path, or any URL containing one.
     */
    public function forPath(string $path): ?Model
    {
        if (preg_match('#/drafts/([a-z-]+)/(\d+)#', $path, $parts) === 1) {
            $dataset = Datasets::for($parts[1]);

            return $dataset?->draftable() ? $dataset->model()::query()->find((int) $parts[2]) : null;
        }

        if (preg_match('#(\d{4})/(\d{2})/(\d{2})/([a-z0-9-]+)#i', $path, $parts) !== 1) {
            return null;
        }

        return $this((int) $parts[1], (int) $parts[2], (int) $parts[3], $parts[4]);
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
