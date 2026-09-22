<?php

namespace App\Presenters\Cards;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Data\SubtitleToken;
use App\Enums\TimelineType;
use App\Models\Fuel;
use App\Presenters\SubtitleText;

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
            type: $this->type(),
            title: $title,
            titleLabel: "Fuel stop, {$title}",
            subtitle: SubtitleText::for($this->tokens($model)),
            subtitleTokens: $this->tokens($model),
            occurredAt: $model->occurred_at,
            range: null,
            meta: CardMeta::fuel(
                map: $model->optimisedUrl('map'),
                mapDark: $model->optimisedUrl('map_dark'),
                brand: $model->brand,
                brandLogo: $model->logo_url,
            ),
            titleTokens: $this->titleTokens($model),
        );
    }

    /**
     * Cost leads, since it is the one figure every row has. The imported rows
     * carry no station, city, brand or coordinates at all, so they name no
     * place rather than inventing one.
     */
    public function title(Fuel $model): string
    {
        return SubtitleText::for($this->titleTokens($model));
    }

    /**
     * @return list<SubtitleToken>
     */
    private function titleTokens(Fuel $model): array
    {
        return [
            SubtitleToken::gbp((float) $model->cost),
            SubtitleToken::text($model->station_name ? "at {$model->station_name}" : 'at the pump', ' '),
        ];
    }

    /**
     * The fill-up as sentences: how much went in and where, then what it cost a
     * litre. Two sentences rather than one with a trailing clause, which is how
     * it would be said out loud.
     */
    /**
     * @return list<SubtitleToken>
     */
    private function tokens(Fuel $model): array
    {
        // "filled up with", not "put ... in": the trailing "in" collides with
        // the city clause ("I put 33 litres in, in Grimsby") whenever there is
        // no price between them.
        $tokens = [
            SubtitleToken::text('I filled up with'),
            SubtitleToken::vol((float) $model->litres, ' '),
            SubtitleToken::text($model->city ? "in {$model->city}." : '.', $model->city ? ' ' : ''),
        ];

        if (! $model->price_per_litre) {
            return $tokens;
        }

        // "Fuel was", not "That was": the "that" pointed at the fill-up, which
        // was not what cost a tenth of a penny.
        return [
            ...$tokens,
            SubtitleToken::text('Fuel was', ' '),
            SubtitleToken::ppl((float) $model->price_per_litre, ' '),
            SubtitleToken::text('.', ''),
        ];
    }

    public function type(): TimelineType
    {
        return TimelineType::Fuel;
    }
}
