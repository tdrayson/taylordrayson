<?php

namespace App\Actions;

use App\Models\Calorie;
use App\Models\TimelineEntry;
use App\Presenters\CardPresenter;
use App\Queries\DayFoodTotals;
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
        $this->warmFoodTotals($entries);

        return $entries
            ->filter(fn (TimelineEntry $entry): bool => $entry->timelineable !== null)
            ->groupBy(fn (TimelineEntry $entry): string => $entry->occurred_at->format('Y-m-d'))
            ->map(fn (Collection $group): array => [
                'label' => $group->first()->occurred_at->format('l j F Y'),
                'date' => $group->first()->occurred_at->format('Y-m-d'),
                'href' => '/'.$group->first()->occurred_at->format('Y/m/d'),
                'items' => $group->map(fn (TimelineEntry $entry): array => $this->cardItem($entry))->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Read every food day on the page in one query. Each food card shows the
     * whole day's totals, so without this they are fetched a card at a time.
     *
     * @param  Collection<int, TimelineEntry>  $entries
     */
    private function warmFoodTotals(Collection $entries): void
    {
        $dates = $entries
            ->filter(fn (TimelineEntry $entry): bool => $entry->timelineable instanceof Calorie)
            ->map(fn (TimelineEntry $entry): string => $entry->timelineable->occurred_at->toDateString())
            ->unique()
            ->values()
            ->all();

        if ($dates !== []) {
            app(DayFoodTotals::class)->warm($dates);
        }
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
            'previews' => $card->meta->previews,
            'favicons' => $card->meta->favicons,
            'segments' => $card->meta->segments,
            'route' => $card->meta->route,
            'media' => $card->meta->media,
            'photos' => $card->meta->photos,
            'polyline' => $card->meta->polyline,
            'map' => $card->meta->map,
            'mapDark' => $card->meta->mapDark,
            'brandLogo' => $card->meta->brandLogo,
            'brand' => $card->meta->brand,
            'address' => $card->meta->address,
            'category' => $card->meta->category,
            'backdrop' => $card->meta->backdrop,
            'range' => $card->range,
            // A day total has no clock reading to show, but keeps a real
            // instant in `datetime` for ordering, microformats and the tooltip.
            'time' => $entry->timelineable->hasClockTime() ? $local['time'] : 'All day',
            'datetime' => $local['iso'],
            'label' => $local['label'],
            'offset' => $local['offset'],
            'url' => $entry->timelineable->url(),
        ];
    }
}
