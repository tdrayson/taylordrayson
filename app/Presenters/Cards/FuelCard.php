<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Enums\TimelineType;
use App\Models\Fuel;
use App\Support\Units;

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

    /**
     * The fill-up as sentences: how much went in and where, then what it cost a
     * litre. Two sentences rather than one with a trailing clause, which is how
     * it would be said out loud.
     */
    private function sentence(Fuel $model): string
    {
        // "filled up with", not "put ... in": the trailing "in" collides with
        // the city clause ("I put 33 litres in, in Grimsby") whenever there is
        // no price between them.
        $sentence = sprintf('I filled up with %s litres', number_format((float) $model->litres, 2));
        $sentence .= $model->city ? " in {$model->city}." : '.';

        if (! $model->price_per_litre) {
            return $sentence;
        }

        // "Fuel was", not "That was": the "that" pointed at the fill-up, which
        // was not what cost a tenth of a penny.
        return $sentence.sprintf(' Fuel was %s a litre.', Units::pencePerLitre($model->price_per_litre));
    }
}
