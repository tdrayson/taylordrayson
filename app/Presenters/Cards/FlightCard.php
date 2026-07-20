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
 * Builds the timeline card for a Flight: route title, distance/cabin class
 * subtitle, and the full route payload (airports, airline, depart/arrive
 * local times) for the flight map.
 */
final class FlightCard
{
    public function present(Flight $model): CardData
    {
        return new CardData(
            type: TimelineType::Flight,
            icon: 'plane',
            title: $this->routeTitle($model),
            titleLabel: null,
            subtitle: $model->distance ? sprintf('%s mi, %s', number_format(Distance::miles($model->distance)), $model->cabin_class?->value) : null,
            // Raw metres (not Distance::miles) so FeedItem.vue converts via useFormat and
            // reacts to the visitor's unit toggle.
            subtitleTokens: $model->distance
                ? [SubtitleToken::dist((int) $model->distance, 0), SubtitleToken::text($model->cabin_class?->value)]
                : null,
            occurredAt: $model->occurred_at,
            accent: 'flight',
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
                map: $model->getFirstMediaUrl('map') ?: null,
                mapDark: $model->getFirstMediaUrl('map_dark') ?: null,
            ),
        );
    }

    /**
     * Route title using city names when the airport relations are loaded
     * (the entry page), falling back to IATA codes otherwise (the feed).
     */
    private function routeTitle(Flight $model): string
    {
        $origin = ($model->relationLoaded('origin') ? $model->origin?->place : null) ?? $model->origin_iata;
        $destination = ($model->relationLoaded('destination') ? $model->destination?->place : null) ?? $model->destination_iata;

        return "{$origin} → {$destination}";
    }
}
