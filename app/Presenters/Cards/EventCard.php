<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Data\PhotoData;
use App\Enums\TimelineType;
use App\Models\Event;

/**
 * Builds the timeline card for an Event: venue/city subtitle, multi-day
 * range, and photos (falling back to the generated location map when the
 * event has no photos of its own).
 */
final class EventCard
{
    public function present(Event $model): CardData
    {
        $parts = array_filter([$model->venue_name, $model->city]);
        $photos = $model->galleryPhotos();

        return new CardData(
            type: TimelineType::Event,
            icon: 'music',
            title: $model->name,
            titleLabel: null,
            subtitle: $parts ? implode(', ', $parts) : null,
            subtitleTokens: null,
            occurredAt: $model->occurred_at,
            accent: 'event',
            range: $model->dateRange(),
            meta: CardMeta::event(
                photos: array_map(
                    fn (array $photo): PhotoData => PhotoData::gallery($photo['src'], $photo['srcset'], $photo['full'], $photo['latitude'], $photo['longitude']),
                    $photos,
                ),
                // Fall back to the generated static location map only when there
                // is no photo to show instead (mirrors the activity route map).
                map: $photos === [] ? $model->getFirstMediaUrl('map') ?: null : null,
                // Dark twin of the same map, rendered by the frontend behind a
                // `dark:` class swap so the theme decides which PNG shows.
                mapDark: $photos === [] ? $model->getFirstMediaUrl('map_dark') ?: null : null,
            ),
        );
    }
}
