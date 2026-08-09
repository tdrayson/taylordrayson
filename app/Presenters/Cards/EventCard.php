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
        // "The Roundhouse in London" reads as a single clause; falls back to
        // whichever one value is present (no dangling "in") when only venue or
        // city is set.
        $subtitle = match (true) {
            $model->venue_name && $model->city => "{$model->venue_name} in {$model->city}",
            default => $model->venue_name ?? $model->city,
        };
        $photos = $model->galleryPhotos();

        return new CardData(
            type: TimelineType::Event,
            icon: 'music',
            title: $model->name,
            titleLabel: null,
            subtitle: $subtitle,
            subtitleTokens: null,
            occurredAt: $model->occurred_at,
            accent: 'event',
            range: $model->dateRange(),
            meta: CardMeta::event(
                photos: array_map(
                    fn (array $photo): PhotoData => PhotoData::gallery($photo['src'], $photo['srcset'], $photo['full'], $photo['latitude'], $photo['longitude']),
                    $photos,
                ),
                // Always carry the location map alongside any photos, like a
                // check-in: the card shows the map and the first photo together
                // (side by side on desktop, a swipeable carousel on mobile).
                map: $model->optimisedUrl('map'),
                // Dark twin of the same map, rendered by the frontend behind a
                // `dark:` class swap so the theme decides which image shows.
                mapDark: $model->optimisedUrl('map_dark'),
            ),
        );
    }
}
