<?php

namespace App\Presenters\Cards;

use App\Data\AirlineData;
use App\Data\CardData;
use App\Data\CardMeta;
use App\Data\RouteData;
use App\Data\RoutePoint;
use App\Data\SubtitleToken;
use App\Enums\TimelineType;
use App\Models\Flight;
use App\Support\Distance;

/**
 * Builds the timeline card for a Flight: route title, a sentence naming the
 * airports, and the full route payload (airports, airline, depart/arrive
 * local times) for the flight map.
 */
final class FlightCard
{
    public function present(Flight $model): CardData
    {
        $cabinClass = $model->cabin_class?->value;
        $lead = $this->lead($model);

        return new CardData(
            type: $this->type(),
            title: $this->title($model),
            titleLabel: null,
            subtitle: $this->sentence($model),
            // Raw metres, not Distance::miles, so FeedItem.vue can convert through
            // useFormat and react to the visitor's unit toggle.
            subtitleTokens: $model->distance
                ? array_values(array_filter([
                    SubtitleToken::text("{$lead} It was"),
                    SubtitleToken::dist((int) $model->distance, 0, ' '),
                    $cabinClass ? SubtitleToken::text("in {$cabinClass}", ' ') : null,
                    SubtitleToken::text('.', ''),
                ]))
                : null,
            occurredAt: $model->occurred_at,
            range: null,
            meta: CardMeta::route(
                route: new RouteData(
                    origin: new RoutePoint(
                        iata: $model->origin_iata,
                        place: $model->relationLoaded('origin') ? $model->origin?->place : null,
                        name: $model->relationLoaded('origin') ? $model->origin?->name : null,
                        lat: $model->relationLoaded('origin') ? $model->origin?->latitude : null,
                        lng: $model->relationLoaded('origin') ? $model->origin?->longitude : null,
                    ),
                    destination: new RoutePoint(
                        iata: $model->destination_iata,
                        place: $model->relationLoaded('destination') ? $model->destination?->place : null,
                        name: $model->relationLoaded('destination') ? $model->destination?->name : null,
                        lat: $model->relationLoaded('destination') ? $model->destination?->latitude : null,
                        lng: $model->relationLoaded('destination') ? $model->destination?->longitude : null,
                    ),
                    depart: $model->departed_local,
                    arrive: $model->arrived_local,
                    distance: Distance::miles($model->distance),
                    duration: $model->duration,
                    airline: $model->relationLoaded('airline') && $model->airline
                        ? new AirlineData(
                            name: $model->airline->name,
                            icon: $model->airline->icon_url,
                            number: trim(($model->airline->iata_code ?: $model->airline_icao).' '.$model->flight_number),
                        )
                        : null,
                ),
                map: $model->optimisedUrl('map'),
                mapDark: $model->optimisedUrl('map_dark'),
            ),
        );
    }

    /** The card sentence, which names both airports and so already stands alone. */
    public function description(Flight $model): string
    {
        return $this->sentence($model);
    }

    /** "I flew from ... to ... with easyJet. It was 800 mi in economy.", the string the tokens compose. */
    private function sentence(Flight $model): string
    {
        $lead = $this->lead($model);
        $cabinClass = $model->cabin_class?->value;

        return $model->distance === null
            ? $lead
            : sprintf(
                '%s It was %s mi%s.',
                $lead,
                number_format(Distance::miles($model->distance)),
                $cabinClass ? " in {$cabinClass}" : '',
            );
    }

    /**
     * Where the flight went and who flew it, by airport name. Codes stand in when the
     * relations are not loaded (feeds, search), since "TFS to LGW" still beats nothing.
     */
    private function lead(Flight $model): string
    {
        $origin = $this->airportName($model, 'origin') ?? $model->origin_iata;
        $destination = $this->airportName($model, 'destination') ?? $model->destination_iata;
        $airline = $model->relationLoaded('airline') && $model->airline ? " with {$model->airline->name}" : '';

        return "I flew from {$origin} to {$destination}{$airline}.";
    }

    /** The airport's name, never its city: that column holds the runway's parish ("Balice" for Kraków). */
    private function airportName(Flight $model, string $relation): ?string
    {
        return $model->relationLoaded($relation) ? $model->{$relation}?->name : null;
    }

    /**
     * Route title using city names when the airport relations are loaded
     * (the entry page), falling back to IATA codes otherwise (the feed).
     */
    public function title(Flight $model): string
    {
        $origin = ($model->relationLoaded('origin') ? $model->origin?->place : null) ?? $model->origin_iata;
        $destination = ($model->relationLoaded('destination') ? $model->destination?->place : null) ?? $model->destination_iata;

        return "{$origin} → {$destination}";
    }

    public function type(): TimelineType
    {
        return TimelineType::Flight;
    }
}
