<?php

namespace App\Content;

use App\Models\Concerns\Timelineable;
use App\Models\TimelineEntry;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class UrlSlugAllocator
{
    /**
     * Stable same-day URL slug (bare base, or base-2 / base-3 on collision).
     * Mirrors timeline spine rules so files and URLs stay aligned.
     */
    public function allocate(Model $model, bool $original = false): string
    {
        $base = $this->baseSlug($model, $original);
        $occurredAt = $this->occurredAt($model, $original);
        $pattern = '/^'.preg_quote($base, '/').'(-\d+)?$/';

        if (method_exists($model, 'timelineEntry')) {
            $model->loadMissing('timelineEntry');
        }

        $entry = method_exists($model, 'timelineEntry') ? $model->timelineEntry : null;

        if (
            ! $original
            && $entry?->url_slug !== null
            && preg_match($pattern, $entry->url_slug) === 1
            && ! $this->takenByAnother($entry, $entry->url_slug, $occurredAt)
        ) {
            return $entry->url_slug;
        }

        $excludeId = $entry?->getKey();

        $taken = TimelineEntry::query()
            ->when($excludeId !== null, fn ($query) => $query->whereKeyNot($excludeId))
            ->whereDate('occurred_at', $occurredAt->toDateString())
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

        return $candidate;
    }

    private function baseSlug(Model $model, bool $original): string
    {
        if (method_exists($model, 'flatFileBaseSlug')) {
            return $model->flatFileBaseSlug($original);
        }

        if ($original) {
            $slug = $model->getOriginal('slug');

            return filled($slug) ? (string) $slug : $model->slug();
        }

        return $model instanceof Timelineable || method_exists($model, 'slug')
            ? $model->slug()
            : (string) ($model->getAttribute('slug') ?? 'entry');
    }

    private function occurredAt(Model $model, bool $original): CarbonInterface
    {
        $value = $original
            ? $model->getOriginal('occurred_at')
            : $model->getAttribute('occurred_at');

        if ($value instanceof CarbonInterface) {
            return $value;
        }

        return Carbon::parse($value);
    }

    private function takenByAnother(?TimelineEntry $entry, string $slug, CarbonInterface $occurredAt): bool
    {
        return TimelineEntry::query()
            ->when($entry?->getKey() !== null, fn ($query) => $query->whereKeyNot($entry->getKey()))
            ->whereDate('occurred_at', $occurredAt->toDateString())
            ->where('url_slug', $slug)
            ->exists();
    }
}
