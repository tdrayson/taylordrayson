<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Enums\TimelineType;
use App\Models\Fuel;

/**
 * Builds the timeline card for a Fuel stop: litres/cost and price-per-litre
 * subtitle plus the generated location map.
 */
final class FuelCard
{
    public function present(Fuel $model): CardData
    {
        // "33 L for £45.06 at £1.359/L": litres and cost read as one clause, with
        // the per-litre price (when known) attached as a second "at ..." clause
        // rather than a comma-joined list item.
        $subtitle = sprintf('%s L for £%.2f', $model->litres, $model->cost);

        if ($model->price_per_litre) {
            $subtitle .= sprintf(' at £%s/L', number_format($model->price_per_litre, 3));
        }

        return new CardData(
            type: TimelineType::Fuel,
            icon: 'fuel',
            title: $model->station_name ?? 'Fuel',
            titleLabel: 'Fuel stop'.($model->station_name ? ', '.$model->station_name : ''),
            subtitle: $subtitle,
            subtitleTokens: null,
            occurredAt: $model->occurred_at,
            accent: 'fuel',
            range: null,
            meta: CardMeta::locationMap(
                map: $model->getFirstMediaUrl('map') ?: null,
                mapDark: $model->getFirstMediaUrl('map_dark') ?: null,
            ),
        );
    }
}
