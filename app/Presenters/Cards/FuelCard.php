<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Enums\TimelineType;
use App\Models\Fuel;

/**
 * Builds the timeline card for a Fuel stop: what it cost and where, with the
 * litres and price per litre written as a sentence beneath.
 */
final class FuelCard
{
    public function present(Fuel $model): CardData
    {
        $title = $this->title($model);

        return new CardData(
            type: TimelineType::Fuel,
            icon: 'fuel',
            title: $title,
            titleLabel: "Fuel stop, {$title}",
            subtitle: $this->sentence($model),
            subtitleTokens: null,
            occurredAt: $model->occurred_at,
            accent: 'fuel',
            range: null,
            meta: CardMeta::fuel(
                map: $model->optimisedUrl('map'),
                mapDark: $model->optimisedUrl('map_dark'),
                brand: $model->brand,
                brandLogo: $model->logo_url,
            ),
        );
    }

    /**
     * Cost leads, since it is the one figure every row has. The imported rows
     * carry no station, city, brand or coordinates at all, so they name no
     * place rather than inventing one.
     */
    private function title(Fuel $model): string
    {
        $cost = '£'.number_format((float) $model->cost, 2);

        return $model->station_name
            ? "{$cost} at {$model->station_name}"
            : "{$cost} at the pump";
    }

    /** The fill-up as a sentence: how much went in, at what price, and where. */
    private function sentence(Fuel $model): string
    {
        // "filled up with", not "put ... in": the trailing "in" collides with
        // the city clause ("I put 33 litres in, in Grimsby") whenever there is
        // no price between them.
        $sentence = sprintf('I filled up with %s litres', number_format((float) $model->litres, 2));

        if ($model->price_per_litre) {
            // Three decimals: pump prices are quoted to a tenth of a penny, the
            // one documented exception to formatting money at two.
            $sentence .= sprintf(' at £%s a litre', number_format((float) $model->price_per_litre, 3));
        }

        if (! $model->city) {
            return "{$sentence}.";
        }

        // The comma only earns its place after a price clause; without one it
        // separates the verb from its own place ("33 litres, in Grimsby").
        return $model->price_per_litre
            ? "{$sentence}, in {$model->city}."
            : "{$sentence} in {$model->city}.";
    }
}
