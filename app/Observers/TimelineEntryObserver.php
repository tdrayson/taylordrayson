<?php

namespace App\Observers;

use App\Models\Calorie;
use App\Models\Concerns\Timelineable;
use App\Models\TimelineEntry;
use App\Support\EntryInstant;
use Illuminate\Database\Eloquent\Model;

class TimelineEntryObserver
{
    public function saved(Model $model): void
    {
        if ($model instanceof Calorie) {
            return;
        }

        if ($model->occurred_at === null) {
            return;
        }

        if ($model instanceof Timelineable && ! $model->shouldAppearOnTimeline()) {
            $model->timelineEntry()->delete();

            return;
        }

        $entry = $model->timelineEntry()->updateOrCreate(
            ['timelineable_type' => $model->getMorphClass(), 'timelineable_id' => $model->getKey()],
            [
                'occurred_at' => $model->occurred_at,
                // Recomputed on every save, so it cannot drift from the pair it
                // is derived from.
                'occurred_utc' => EntryInstant::utc($model->occurred_at, $this->timezoneOf($model)),
                'ends_at' => $model->getAttribute('ends_at'),
            ],
        );

        $this->ensureUrlSlug($model, $entry);
    }

    /**
     * Assign the entry's URL slug once at write time: the first same-day
     * holder keeps the bare slug, collisions get -2/-3... Stored so URLs
     * never reshuffle when earlier entries are backfilled or deleted; only
     * a changed base slug or a date move triggers reassignment.
     */
    /**
     * The entry's own zone. Read through the accessor rather than as an
     * attribute: Timelineable models expose `timezone()` as a method, which
     * getAttribute() would try to resolve as a relationship.
     */
    private function timezoneOf(Model $model): ?string
    {
        return method_exists($model, 'timezone') ? $model->timezone() : null;
    }

    private function ensureUrlSlug(Model $model, TimelineEntry $entry): void
    {
        $base = $model->slug();
        $pattern = '/^'.preg_quote($base, '/').'(-\d+)?$/';

        if ($entry->url_slug !== null
            && preg_match($pattern, $entry->url_slug) === 1
            && ! $this->takenByAnother($entry, $entry->url_slug)) {
            return;
        }

        $taken = TimelineEntry::query()
            ->whereKeyNot($entry->getKey())
            ->whereDate('occurred_at', $entry->occurred_at->toDateString())
            ->where(fn ($query) => $query->where('url_slug', $base)->orWhere('url_slug', 'like', "{$base}-%"))
            ->pluck('url_slug')
            ->filter(fn (?string $slug): bool => $slug !== null && preg_match($pattern, $slug) === 1)
            ->all();

        $candidate = $base;
        $suffix = 1;

        while (in_array($candidate, $taken, true)) {
            $suffix++;
            $candidate = "{$base}-{$suffix}";
        }

        $entry->url_slug = $candidate;
        $entry->save();
    }

    private function takenByAnother(TimelineEntry $entry, string $slug): bool
    {
        return TimelineEntry::query()
            ->whereKeyNot($entry->getKey())
            ->whereDate('occurred_at', $entry->occurred_at->toDateString())
            ->where('url_slug', $slug)
            ->exists();
    }

    public function deleted(Model $model): void
    {
        if ($model instanceof Calorie) {
            return;
        }

        $model->timelineEntry()?->delete();
    }
}
