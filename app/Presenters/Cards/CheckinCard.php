<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Data\PhotoData;
use App\Enums\TimelineType;
use App\Models\Checkin;

/**
 * Builds the timeline card for a Checkin: the note when there is one, else the
 * venue's category and city, plus the generated location map.
 */
final class CheckinCard
{
    public function present(Checkin $model): CardData
    {
        $address = collect([$model->address, $model->city, $model->county, $model->country])
            ->filter()
            ->implode(', ');

        $subtitle = $model->description ?: $this->placeCaption($model);

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

    /**
     * What the place is, for the 1,801 check-ins carrying no note of their own,
     * which showed nothing at all before.
     *
     * A noun phrase rather than a sentence: Foursquare's category vocabulary
     * includes Road, Platform and Town, and any verb general enough to cover
     * "a coffee shop" reads wrong against those.
     */
    private function placeCaption(Checkin $model): ?string
    {
        if (! $model->category) {
            return $model->city ? "In {$model->city}." : null;
        }

        // Category kept exactly as stored: lowercasing it would mangle the
        // proper nouns in the vocabulary ("Irish Pub", "Italian Restaurant").
        $article = str_contains('aeiou', strtolower($model->category[0])) ? 'An' : 'A';
        $caption = "{$article} {$model->category}";

        return $model->city ? "{$caption} in {$model->city}." : "{$caption}.";
    }
}
