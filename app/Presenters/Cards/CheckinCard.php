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
        // The full address always renders beneath the map, whether or not the
        // check-in has a note. Country is included so overseas check-ins read
        // correctly; domestic ones simply tail with "United Kingdom".
        $address = collect([$model->address, $model->city, $model->county, $model->country])
            ->filter()
            ->implode(', ');

        // The subtitle is the check-in's own note/shout and nothing else; the
        // address renders in its own row beneath the map.
        $subtitle = $model->description ?: null;

        // An attached Swarm event (a gig, screening, race meet) becomes the
        // headline: "Bug Jam 2026 at Santa Pod Raceway". The URL slug still comes
        // from the venue name (Checkin::slug()), so this is display-only.
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
