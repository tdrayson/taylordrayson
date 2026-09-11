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
            (bool) $model->venue_name && (bool) $model->city => "I went to {$model->venue_name} in {$model->city}.",
            (bool) $model->venue_name => "I went to {$model->venue_name}.",
            (bool) $model->city => "I was in {$model->city}.",
            default => null,
        };
        $photos = $model->galleryPhotos();

        return new CardData(
            type: $this->type(),
            title: $this->title($model),
            titleLabel: null,
            subtitle: $subtitle,
            subtitleTokens: null,
            occurredAt: $model->occurred_at,
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

    public function title(Event $model): string
    {
        return $model->name;
    }

    public function type(): TimelineType
    {
        return TimelineType::Event;
    }
}
