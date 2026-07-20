<?php

namespace App\Actions;

use App\Enums\MediaType;
use App\Models\Media;
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
                'items' => $this->collapseEpisodes($group, $group->first()->occurred_at->format('Y-m-d')),
            ])
            ->values()
            ->all();
    }

    /**
     * Fold a day's group of timeline entries into feed cards, collapsing
     * multiple same-show episode watches into a single synthesized "binge"
     * card. Everything else (films, single episodes, non-media entries)
     * renders through the normal cardItem() path unchanged.
     *
     * @param  Collection<int, TimelineEntry>  $group
     * @return array<int, array<string, mixed>>
     */
    private function collapseEpisodes(Collection $group, string $date): array
    {
        // Count episode watches per series this day, so a series with more than
        // one becomes a single "binge" card while singletons stay normal.
        $episodeCounts = $group
            ->filter(fn (TimelineEntry $entry): bool => $entry->timelineable instanceof Media
                && $entry->timelineable->type === MediaType::TvEpisode
                && $entry->timelineable->series_id !== null)
            ->countBy(fn (TimelineEntry $entry): int => $entry->timelineable->series_id);

        // Walk the group in its original order so the caller's sort direction
        // (ascending on the year/month pages, descending on the main feed) is
        // preserved. The binge card lands where the show's first episode was.
        $emitted = [];
        $items = [];

        foreach ($group as $entry) {
            $media = $entry->timelineable;
            $seriesId = $media instanceof Media && $media->type === MediaType::TvEpisode ? $media->series_id : null;
            $isBinge = $seriesId !== null && ($episodeCounts[$seriesId] ?? 0) > 1;

            if (! $isBinge) {
                $items[] = $this->cardItem($entry);

                continue;
            }

            if (isset($emitted[$seriesId])) {
                continue;
            }

            $emitted[$seriesId] = true;
            $items[] = $this->synthesiseSeriesCard(
                $group->filter(fn (TimelineEntry $candidate): bool => $candidate->timelineable instanceof Media
                    && $candidate->timelineable->type === MediaType::TvEpisode
                    && $candidate->timelineable->series_id === $seriesId),
                $date,
            );
        }

        return $items;
    }

    /**
     * Build one feed card summarising N same-day watches of the same show,
     * mirroring cardItem()'s key shape (so FeedItem.vue renders it unchanged)
     * plus a `count`. Links to the series page anchored at this day's watch
     * section rather than any single episode.
     *
     * Note: reads $entry->timelineable->series lazily per collapsed group
     * (not eager-loaded), which is acceptable given the small number of
     * entries per day.
     *
     * @param  Collection<int, TimelineEntry>  $entries
     * @return array<string, mixed>
     */
    private function synthesiseSeriesCard(Collection $entries, string $date): array
    {
        $first = $entries->first()->timelineable;
        $series = $first->series;
        $count = $entries->count();
        $local = LocalTime::for($first->occurredAtForDisplay(), $first->timezone());

        return [
            'iconKey' => 'media',
            'accent' => 'media',
            'title' => $series?->title ?? $first->meta['show_title'] ?? $first->title,
            'titleLabel' => null,
            'meta' => "{$count} episodes",
            'metaTokens' => null,
            'body' => null,
            'segments' => null,
            'route' => null,
            'media' => null,
            'photos' => null,
            'polyline' => null,
            'map' => null,
            'mapDark' => null,
            'range' => null,
            'count' => $count,
            'time' => $local['time'],
            'datetime' => $local['iso'],
            'label' => $local['label'],
            'offset' => $local['offset'],
            'url' => $series ? "/media/tv/{$series->slug}#watch-{$date}" : null,
        ];
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
            'brandLogo' => $card->meta->brandLogo,
            'brand' => $card->meta->brand,
            'range' => $card->range,
            'time' => $local['time'],
            'datetime' => $local['iso'],
            'label' => $local['label'],
            'offset' => $local['offset'],
            'url' => $entry->timelineable->url(),
        ];
    }
}
