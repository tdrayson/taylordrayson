<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Data\PhotoData;
use App\Enums\TimelineType;
use App\Models\Place;

/**
 * Builds the timeline card for a Place: "at Cineworld", the note when there is
 * one, and the generated location map.
 */
final class PlaceCard
{
    public function present(Place $model): CardData
    {
        $address = collect([$model->address, $model->city, $model->county, $model->country])
            ->filter()
            ->implode(', ');

        $title = $this->title($model);

        return new CardData(
            type: $this->type(),
            title: $title,
            // "Check-in at Cineworld" reads straight through; an event title
            // already names something, so it takes a comma.
            titleLabel: $model->event_name ? "Check-in, {$title}" : "Check-in {$title}",
            // The note only. A check-in without one says nothing here: what the
            // place is arrives as its category, which is Foursquare's word and
            // not a sentence to be written around.
            subtitle: $model->description ?: null,
            subtitleTokens: null,
            occurredAt: $model->occurred_at,
            range: null,
            meta: CardMeta::place(
                photos: array_map(
                    fn (array $photo): PhotoData => PhotoData::gallery($photo['id'], $photo['src'], $photo['srcset'], $photo['full'], $photo['alt'], $photo['caption'], $photo['latitude'], $photo['longitude']),
                    $model->galleryPhotos(),
                ),
                map: $model->optimisedUrl('map'),
                mapDark: $model->optimisedUrl('map_dark'),
                address: $address !== '' ? $address : null,
                category: $model->type,
            ),
        );
    }

    /**
     * Display-only: the URL slug still comes from the venue (Place::slug()).
     */
    public function title(Place $model): string
    {
        return $model->event_name
            ? "{$model->event_name} at {$model->venue_name}"
            : "at {$model->venue_name}";
    }

    public function type(): TimelineType
    {
        return TimelineType::Place;
    }
}
