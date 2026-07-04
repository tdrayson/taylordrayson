<?php

namespace App\Actions;

use App\Models\TimelineEntry;
use App\Support\LocalTime;
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
        // Seed the inverse relation so url() reads the spine's url_slug
        // without a lazy query per card.
        $entry->timelineable->setRelation('timelineEntry', $entry);

        $card = $entry->timelineable->card();
        $local = LocalTime::for($entry->occurred_at, $entry->timelineable->timezone(), LocalTime::isDayLevel($card['type']));

        return [
            'iconKey' => $card['type'],
            'accent' => $card['accent'],
            'title' => $card['title'],
            'meta' => Text::excerpt($card['subtitle'], 160),
            'body' => $card['meta']['body'] ?? null,
            'segments' => $card['meta']['segments'] ?? null,
            'route' => $card['meta']['route'] ?? null,
            'media' => $card['meta']['media'] ?? null,
            'photos' => $card['meta']['photos'] ?? null,
            'polyline' => $card['meta']['polyline'] ?? null,
            'time' => $local['time'],
            'datetime' => $local['iso'],
            'label' => $local['label'],
            'offset' => $local['offset'],
            'url' => $entry->timelineable->url(),
        ];
    }
}
