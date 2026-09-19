<?php

namespace App\Presenters\Exports;

use App\Data\Aspects\Geometry;
use App\Data\Aspects\Span;
use App\Data\ExportData;
use App\Data\ExportField;
use App\Data\ExportInstant;
use App\Data\ExportLink;
use App\Enums\TimelineType;
use App\Models\Flight;
use App\Presenters\CardPresenter;
use App\Presenters\EntryDescription;
use App\Support\Distance;
use App\Support\Units;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * A flight as an export: the ticket's own facts, the two airports as
 * structured values, and a route geometry and time span so .geojson and .ics
 * become available.
 */
final class FlightExport
{
    public function present(Flight $model): ExportData
    {
        $model->loadMissing('airline', 'origin', 'destination');

        $card = CardPresenter::for($model);
        $departed = $this->instant($model->departed_local, $model->departure_timezone);
        $arrived = $this->instant($model->arrived_local, $model->arrival_timezone);

        return new ExportData(
            type: TimelineType::Flight,
            url: url($model->url()),
            title: $card->title,
            summary: EntryDescription::for($model, $card),
            occurred: $model->occurred_at === null ? null : ExportInstant::for($model->occurred_at, $model->timezone()),
            fields: array_values(array_filter([
                ExportField::maybe('flight', 'Flight', $this->flightIdentifier($model), $this->flightCode($model)),
                $this->airport($model, 'origin', 'From'),
                $this->airport($model, 'destination', 'To'),
                ExportField::maybe('departed', 'Departed', $this->localTime($departed, $model->origin?->city ?? $model->origin_iata), $departed?->toIso8601String()),
                ExportField::maybe('arrived', 'Arrived', $this->localTime($arrived, $model->destination?->city ?? $model->destination_iata), $arrived?->toIso8601String()),
                ExportField::maybe('duration', 'Duration', $model->duration === null ? null : Units::humanDuration($model->duration), $model->duration),
                ExportField::maybe('distance', 'Distance', $model->distance === null ? null : number_format(Distance::miles($model->distance)).' miles', $model->distance),
                ExportField::maybe('cabin', 'Cabin', $model->cabin_class?->label(), $model->cabin_class?->value),
                ExportField::maybe('reason', 'Reason', $model->reason?->label(), $model->reason?->value),
            ])),
            links: [
                ...array_values(array_filter([
                    $model->airline === null ? null : ExportLink::make('airline', 'Airline', $model->airline->name, '/flights/'.Str::slug($model->airline->name)),
                ])),
                ...CommonLinks::for($model),
            ],
            aspects: array_filter([
                Geometry::class => $this->route($model),
                Span::class => $departed === null || $arrived === null ? null : Span::across($departed, $arrived, $model->destination?->name),
            ]),
        );
    }

    /** The IATA (or ICAO) code and number, e.g. "U2 8824": the machine value behind the flight field. */
    private function flightCode(Flight $model): ?string
    {
        if ($model->flight_number === null) {
            return null;
        }

        $code = $model->airline?->iata_code ?: $model->airline_icao;

        return trim("{$code} {$model->flight_number}");
    }

    private function flightIdentifier(Flight $model): ?string
    {
        $code = $this->flightCode($model);

        return $code === null ? null : trim(($model->airline?->name ?? '')." {$code}");
    }

    private function localTime(?CarbonImmutable $instant, ?string $place): ?string
    {
        return $instant === null ? null : $instant->format('H:i').' '.$place;
    }

    private function airport(Flight $model, string $relation, string $label): ?ExportField
    {
        $airport = $model->{$relation};
        $iata = $relation === 'origin' ? $model->origin_iata : $model->destination_iata;

        if ($airport === null) {
            return ExportField::maybe($relation, $label, $iata, $iata);
        }

        return ExportField::make($relation, $label, "{$airport->name} ({$airport->iata_code})", [
            'iata' => $airport->iata_code,
            'icao' => $airport->icao_code,
            'lat' => (float) $airport->latitude,
            'lng' => (float) $airport->longitude,
        ]);
    }

    private function route(Flight $model): ?Geometry
    {
        if ($model->origin?->latitude === null || $model->destination?->latitude === null) {
            return null;
        }

        return Geometry::lineString([
            [(float) $model->origin->latitude, (float) $model->origin->longitude],
            [(float) $model->destination->latitude, (float) $model->destination->longitude],
        ]);
    }

    /** A stored wall-clock string read in its own zone, so the offset is right and DST-aware. */
    private function instant(?string $wallClock, ?string $timezone): ?CarbonImmutable
    {
        return $wallClock === null || $timezone === null
            ? null
            : CarbonImmutable::parse($wallClock, $timezone);
    }
}
