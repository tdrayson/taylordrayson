<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
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
        // "Coffee Shop in London" reads as a single clause; falls back to whichever
        // one value is present (no dangling "in") when only category or city is set.
        $subtitle = match (true) {
            $model->category && $model->city => "{$model->category} in {$model->city}",
            default => $model->category ?? $model->city,
        };

        return new CardData(
            type: TimelineType::Checkin,
            icon: 'map-pin',
            title: $model->venue_name,
            titleLabel: null,
            subtitle: $subtitle,
            subtitleTokens: null,
            occurredAt: $model->occurred_at,
            accent: 'checkin',
            range: null,
            meta: CardMeta::locationMap(
                map: $model->getFirstMediaUrl('map') ?: null,
                mapDark: $model->getFirstMediaUrl('map_dark') ?: null,
            ),
        );
    }
}
