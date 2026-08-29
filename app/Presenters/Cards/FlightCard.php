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
        $cabinClass = $model->cabin_class?->value;

        $lead = $this->lead($model);

        $subtitle = $model->distance === null
            ? $lead
            : sprintf(
                '%s It was %s mi%s.',
                $lead,
                number_format(Distance::miles($model->distance)),
                $cabinClass ? " in {$cabinClass}" : '',
            );

        return new CardData(
            type: TimelineType::Flight,
            icon: 'plane',
            title: $this->routeTitle($model),
            titleLabel: null,
            subtitle: $subtitle,
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
                map: $model->optimisedUrl('map'),
                mapDark: $model->optimisedUrl('map_dark'),
            ),
        );
    }

    /**
     * Where the flight went and who flew it. The route is named again rather
     * than left to the title, because the surfaces this string reaches (feeds,
     * search, link previews) show the IATA codes when the relations are not
     * loaded, and "TFS to LGW" names nothing to a reader.
     */
    private function lead(Flight $model): string
    {
        $origin = ($model->relationLoaded('origin') ? $model->origin?->city : null) ?? $model->origin_iata;
        $destination = ($model->relationLoaded('destination') ? $model->destination?->city : null) ?? $model->destination_iata;
        $airline = $model->relationLoaded('airline') && $model->airline ? " with {$model->airline->name}" : '';

        return "I flew from {$origin} to {$destination}{$airline}.";
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
