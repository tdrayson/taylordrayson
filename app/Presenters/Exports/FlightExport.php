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
use App\Presenters\Exports\Sheets\FlightSheet;
use App\Support\Distance;
use App\Support\GreatCircle;
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
    public function sheet(): FlightSheet
    {
        return new FlightSheet;
    }

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
                ExportField::maybe('flight_code', 'Flight no.', $this->flightCode($model), $this->flightCode($model)),
                ExportField::maybe('airline', 'Airline', $model->airline?->name),
                ...$this->airport($model, 'origin', 'From'),
                ...$this->airport($model, 'destination', 'To'),
                ExportField::maybe('departed', 'Departed', $this->localTime($departed, $model->origin?->city ?? $model->origin_iata), $departed?->toIso8601String()),
                ExportField::maybe('arrived', 'Arrived', $this->localTime($arrived, $model->destination?->city ?? $model->destination_iata), $arrived?->toIso8601String()),
                ExportField::maybe('departs_time', 'Departs', $departed?->format('H:i')),
                ExportField::maybe('arrives_time', 'Arrives', $arrived?->format('H:i')),
                ExportField::maybe('date', 'Date', $model->occurred_at?->format('d M Y')),
                ExportField::maybe('duration', 'Duration', $model->duration === null ? null : Units::humanDuration($model->duration), $model->duration),
                ExportField::maybe('distance', 'Distance', $model->distance === null ? null : number_format(Distance::miles($model->distance)).' miles', $model->distance),
                ExportField::maybe('cabin', 'Cabin', $model->cabin_class?->label(), $model->cabin_class?->value),
                ExportField::maybe('reason', 'Reason', $model->reason?->label(), $model->reason?->value),
                ExportField::maybe('passenger', 'Passenger', $this->passenger()),
                ExportField::make('ticket_number', 'Ticket no.', str_pad((string) $model->id, 10, '0', STR_PAD_LEFT), $model->id),
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

    /** The owner's name in airline surname-first form, e.g. "DRAYSON / TAYLOR" for the boarding pass. */
    private function passenger(): string
    {
        $parts = explode(' ', trim((string) config('identity.name')));
        $surname = array_pop($parts);

        return mb_strtoupper(trim("{$surname} / ".implode(' ', $parts)));
    }

    /**
     * An airport as three fields: the full name and code together (for other
     * formats), the bare code alone, and the city alone. The boarding pass
     * leads with the code, large, so it needs that on its own rather than
     * parsed back out of the combined display.
     *
     * @return list<ExportField>
     */
    private function airport(Flight $model, string $relation, string $label): array
    {
        $airport = $model->{$relation};
        $iata = $relation === 'origin' ? $model->origin_iata : $model->destination_iata;
        $codeKey = "{$relation}_code";
        $cityKey = "{$relation}_city";

        if ($airport === null) {
            return array_values(array_filter([
                ExportField::maybe($relation, $label, $iata, $iata),
                ExportField::maybe($codeKey, 'Code', $iata, $iata),
            ]));
        }

        return array_values(array_filter([
            ExportField::make($relation, $label, "{$airport->name} ({$airport->iata_code})", [
                'iata' => $airport->iata_code,
                'icao' => $airport->icao_code,
                'lat' => (float) $airport->latitude,
                'lng' => (float) $airport->longitude,
            ]),
            ExportField::maybe($codeKey, 'Code', $airport->iata_code ?: $airport->icao_code, $airport->iata_code ?: $airport->icao_code),
            ExportField::maybe($cityKey, 'City', $airport->city, $airport->city),
        ]));
    }

    /** The great-circle path between the airports, not a straight line, since that's the route actually flown. */
    private function route(Flight $model): ?Geometry
    {
        if ($model->origin?->latitude === null || $model->destination?->latitude === null) {
            return null;
        }

        $segments = GreatCircle::segments(
            (float) $model->origin->latitude, (float) $model->origin->longitude,
            (float) $model->destination->latitude, (float) $model->destination->longitude,
        );

        return count($segments) === 1
            ? Geometry::lineString($segments[0])
            : Geometry::multiLineString($segments);
    }

    /** A stored wall-clock string read in its own zone, so the offset is right and DST-aware. */
    private function instant(?string $wallClock, ?string $timezone): ?CarbonImmutable
    {
        return $wallClock === null || $timezone === null
            ? null
            : CarbonImmutable::parse($wallClock, $timezone);
    }
}
