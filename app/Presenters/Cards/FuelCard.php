<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Enums\TimelineType;
use App\Models\Fuel;
use App\Support\Units;

/**
 * Builds the timeline card for a Fuel stop: where I filled up as the title and
 * what it cost as the sentence beneath.
 */
final class FuelCard
{
    public function present(Fuel $model): CardData
    {
        $title = $this->title($model);

        return new CardData(
            type: $this->type(),
            title: $title,
            titleLabel: "Fuel stop, {$title}",
            subtitle: $this->sentence($model),
            subtitleTokens: null,
            occurredAt: $model->occurred_at,
            range: null,
            meta: CardMeta::fuel(
                map: $model->optimisedUrl('map'),
                mapDark: $model->optimisedUrl('map_dark'),
                brand: $model->brand,
                brandLogo: $model->logo_url,
            ),
        );
    }

    /** Title and sentence together, since the title already reads as one. */
    public function description(Fuel $model): string
    {
        return "{$this->title($model)}. {$this->sentence($model)}";
    }

    /**
     * "I filled up the car at BP in Croydon": the brand is what anyone calls a garage,
     * the forecourt name only when there is no brand. The imported rows name no place.
     */
    public function title(Fuel $model): string
    {
        $garage = $model->brand ?: $model->station_name;
        $at = $garage ? " at {$garage}" : '';
        $in = $model->city ? " in {$model->city}" : '';

        return "I filled up the car{$at}{$in}";
    }

    /** "It cost £50.64 for 31.28 litres, which is 161.9p a litre.", without the price clause when unknown. */
    private function sentence(Fuel $model): string
    {
        $paid = sprintf(
            'It cost £%s for %s litres',
            number_format((float) $model->cost, 2),
            number_format((float) $model->litres, 2),
        );

        return $model->price_per_litre
            ? sprintf('%s, which is %s a litre.', $paid, Units::pencePerLitre($model->price_per_litre))
            : "{$paid}.";
    }

    public function type(): TimelineType
    {
        return TimelineType::Fuel;
    }
}
