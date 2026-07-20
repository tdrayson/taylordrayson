<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Enums\TimelineType;
use App\Models\Fuel;

/**
 * Builds the timeline card for a Fuel stop: litres/cost and price-per-litre
 * subtitle plus the generated location map and brand logo.
 */
final class FuelCard
{
    public function present(Fuel $model): CardData
    {
        $parts = array_filter([
            sprintf('%sL, £%.2f', $model->litres, $model->cost),
            $model->price_per_litre ? sprintf('£%s / L', number_format($model->price_per_litre, 3)) : null,
        ]);

        return new CardData(
            type: TimelineType::Fuel,
            icon: 'fuel',
            title: $model->station_name ?? 'Fuel',
            titleLabel: 'Fuel stop'.($model->station_name ? ', '.$model->station_name : ''),
            subtitle: implode(', ', $parts),
            subtitleTokens: null,
            occurredAt: $model->occurred_at,
            accent: 'fuel',
            range: null,
            meta: CardMeta::fuel(
                map: $model->getFirstMediaUrl('map') ?: null,
                mapDark: $model->getFirstMediaUrl('map_dark') ?: null,
                brand: $model->brand,
                brandLogo: $model->logo_url,
            ),
        );
    }
}
