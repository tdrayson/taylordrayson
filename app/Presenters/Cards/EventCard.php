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
                // Carried alongside any photos: the card shows map and first photo together.
                map: $model->optimisedUrl('map'),
                mapDark: $model->optimisedUrl('map_dark'),
            ),
        );
    }
}
