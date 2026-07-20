<?php

namespace App\Actions;

use App\Models\TimelineEntry;
use App\Presenters\CardPresenter;
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

        $card = CardPresenter::for($entry->timelineable);
        $local = LocalTime::for($entry->timelineable->occurredAtForDisplay(), $entry->timelineable->timezone());

        return [
            'iconKey' => $card->type->value,
            'accent' => $card->accent,
            'title' => $card->title,
            'titleLabel' => $card->titleLabel,
            'meta' => Text::excerpt($card->subtitle, 240),
            'metaTokens' => $card->subtitleTokens,
            'body' => $card->meta->body,
            'segments' => $card->meta->segments,
            'route' => $card->meta->route,
            'media' => $card->meta->media,
            'photos' => $card->meta->photos,
            'polyline' => $card->meta->polyline,
            'map' => $card->meta->map,
            'mapDark' => $card->meta->mapDark,
            'range' => $card->range,
            'time' => $local['time'],
            'datetime' => $local['iso'],
            'label' => $local['label'],
            'offset' => $local['offset'],
            'url' => $entry->timelineable->url(),
        ];
    }
}
