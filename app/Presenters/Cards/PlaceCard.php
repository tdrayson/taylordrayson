<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Data\PhotoData;
use App\Enums\TimelineType;
use App\Models\Place;
use App\Support\Text;

/**
 * Builds the timeline card for a Place: "at Cineworld", the note as its
 * summary, and the generated location map.
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
            subtitle: null,
            subtitleTokens: null,
            occurredAt: $model->occurred_at,
            range: null,
            meta: CardMeta::place(
                photos: array_map(
                    fn (array $photo): PhotoData => PhotoData::gallery($photo['src'], $photo['srcset'], $photo['full'], $photo['latitude'], $photo['longitude']),
                    $model->galleryPhotos(),
                ),
                map: $model->optimisedUrl('map'),
                mapDark: $model->optimisedUrl('map_dark'),
                address: $address !== '' ? $address : null,
                category: $model->type,
            ),
            summary: Text::prose($model->description),
        );
    }

    /**
     * The note with the place hung off the end, else a check-in sentence. Foursquare's
     * category stays out: its vocabulary ("Gym and Studio", "Road") does not read as prose.
     */
    public function description(Place $model): string
    {
        $note = Text::prose($model->description);
        $place = collect([$model->venue_name, $model->city])->filter()->implode(', ');

        if ($note !== null) {
            return $place === '' ? $note : $this->append($note, "at {$place}");
        }

        if (! $model->venue_name) {
            return '';
        }

        return "I checked in at {$model->venue_name}".($model->city ? " in {$model->city}" : '').'.';
    }

    /**
     * Attach a clause to a note without editing it; a note ending on a stop or an emoji gets
     * it as its own sentence. A closing quote or bracket after the stop still counts as ended.
     */
    private function append(string $note, string $clause): string
    {
        $ended = preg_match('/(?:[.!?…][\x{22}\x{27}\x{2019}\x{201D}\)]*|\p{Extended_Pictographic}[\x{FE0F}\x{200D}\x{1F3FB}-\x{1F3FF}\p{Extended_Pictographic}]*)$/u', $note) === 1;

        return $ended ? "{$note} ".ucfirst($clause).'.' : "{$note} {$clause}.";
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
