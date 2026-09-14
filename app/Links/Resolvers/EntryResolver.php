<?php

namespace App\Links\Resolvers;

use App\Data\LinkPreviewData;
use App\Enums\EntryStatus;
use App\Links\LinkResolver;
use App\Models\Scopes\ListedScope;
use App\Models\TimelineEntry;
use App\Presenters\CardPresenter;
use App\Presenters\EntryDescription;

/**
 * An entry permalink, /YYYY/MM/DD/slug.
 */
class EntryResolver implements LinkResolver
{
    public function resolve(string $path): ?LinkPreviewData
    {
        if (preg_match('#^/(\d{4})/(\d{2})/(\d{2})/([a-z0-9-]+)$#', $path, $matches) !== 1) {
            return null;
        }

        $model = TimelineEntry::query()->withoutGlobalScope(ListedScope::class)
            ->with('entry')
            ->whereDate('occurred_at', "{$matches[1]}-{$matches[2]}-{$matches[3]}")
            ->where('url_slug', $matches[4])
            ->first()?->entry;

        if ($model === null || ! $model->isViewableBy(null)) {
            return null;
        }

        $card = CardPresenter::for($model);
        // Same order the timeline card prefers: a real photo, then a poster or
        // artwork, then the stored route map. Only the map has a dark variant.
        $cover = $card->meta->photos[0]->src ?? $card->meta->media?->thumbnail ?? $card->meta->map;

        return LinkPreviewData::entry(
            url: $path,
            title: CardPresenter::publicTitle($model, $card),
            excerpt: $model->status === EntryStatus::Private ? EntryDescription::for($model, $card) : ($card->summary ?? $card->subtitle),
            type: $card->type->value,
            accent: $card->accent,
            date: $model->occurredAtForDisplay()?->toDateString(),
            cover: $cover,
            coverDark: $cover === $card->meta->map ? $card->meta->mapDark : null,
        );
    }
}
