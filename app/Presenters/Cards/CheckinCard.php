<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Data\PhotoData;
use App\Enums\TimelineType;
use App\Models\Checkin;
use Illuminate\Support\Str;

/**
 * Builds the timeline card for a Checkin: category/city subtitle plus the
 * generated location map.
 */
final class CheckinCard
{
    public function present(Checkin $model): CardData
    {
        // Full address plus the category as a kebab hashtag (matching the /places
        // category taxonomy), shown under the venue when there is no personal
        // note. Country is dropped: nearly every check-in is domestic, so it is
        // noise on the card; the single-entry page still shows the full address.
        $address = collect([$model->address, $model->city, $model->county])
            ->filter()
            ->implode(', ');
        $hashtag = $model->category ? '#'.Str::slug($model->category) : null;
        $location = trim(implode(' ', array_filter([$address, $hashtag])));

        // Lead with the check-in's own note/shout when it has one so a personal
        // comment sits directly under the place name; otherwise fall back to the
        // address + category. The location map still carries the "where" either way.
        $subtitle = $model->description ?: ($location !== '' ? $location : null);

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
                map: $model->getFirstMediaUrl('map') ?: null,
                mapDark: $model->getFirstMediaUrl('map_dark') ?: null,
            ),
        );
    }
}
