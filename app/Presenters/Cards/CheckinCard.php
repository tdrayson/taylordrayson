<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Data\PhotoData;
use App\Enums\TimelineType;
use App\Models\Checkin;

/**
 * Builds the timeline card for a Checkin: category/city subtitle plus the
 * generated location map.
 */
final class CheckinCard
{
    public function present(Checkin $model): CardData
    {
        $address = collect([$model->address, $model->city, $model->county, $model->country])
            ->filter()
            ->implode(', ');

        $subtitle = $model->description ?: null;

        // Display-only: the URL slug still comes from the venue (Checkin::slug()).
        $title = $model->event_name
            ? "{$model->event_name} at {$model->venue_name}"
            : $model->venue_name;

        return new CardData(
            type: TimelineType::Checkin,
            icon: 'map-pin',
            title: $title,
            titleLabel: null,
            subtitle: $subtitle,
            subtitleTokens: null,
            occurredAt: $model->occurred_at,
            accent: 'checkin',
            range: null,
            meta: CardMeta::checkin(
                photos: array_map(
                    fn (array $photo): PhotoData => PhotoData::gallery($photo['src'], $photo['srcset'], $photo['full'], $photo['latitude'], $photo['longitude']),
                    $model->galleryPhotos(),
                ),
                map: $model->optimisedUrl('map'),
                mapDark: $model->optimisedUrl('map_dark'),
                address: $address !== '' ? $address : null,
            ),
        );
    }
}
