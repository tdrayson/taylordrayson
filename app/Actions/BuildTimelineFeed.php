<?php

namespace App\Actions;

use App\Models\TimelineEntry;
use App\Support\Text;
use Illuminate\Support\Collection;

class BuildTimelineFeed
{
    /**
     * Group timeline entries into day buckets for the feed UI. Entries should
     * already be ordered (the iteration order is preserved across days and items).
     *
     * @param  Collection<int, TimelineEntry>  $entries
     * @return array<int, array{label: string, href: string, items: array<int, array<string, mixed>>}>
     */
    public function groupByDay(Collection $entries): array
    {
        return $entries
            ->filter(fn (TimelineEntry $entry): bool => $entry->timelineable !== null)
            ->groupBy(fn (TimelineEntry $entry): string => $entry->occurred_at->format('Y-m-d'))
            ->map(fn (Collection $group): array => [
                'label' => $group->first()->occurred_at->format('l j F Y'),
                'date' => $group->first()->occurred_at->format('Y-m-d'),
                'href' => '/'.$group->first()->occurred_at->format('Y/m/d'),
                'items' => $group->map(fn (TimelineEntry $entry): array => $this->cardItem($entry))->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Shape a single timeline entry into the feed card payload consumed by FeedItem.vue.
     *
     * @return array<string, mixed>
     */
    public function cardItem(TimelineEntry $entry): array
    {
        $card = $entry->timelineable->card();

        return [
            'iconKey' => $card['type'],
            'accent' => $card['accent'],
            'title' => $card['title'],
            'meta' => Text::excerpt($card['subtitle'], 160),
            'segments' => $card['meta']['segments'] ?? null,
            'route' => $card['meta']['route'] ?? null,
            'media' => $card['meta']['media'] ?? null,
            'polyline' => $card['meta']['polyline'] ?? null,
            'time' => $entry->occurred_at->format('g:ia'),
            'datetime' => $entry->occurred_at->toIso8601String(),
            'url' => $entry->timelineable->url(),
        ];
    }
}
