<?php

namespace App\Fields;

use App\Data\FieldData;
use App\Enums\CabinClass;
use App\Enums\FieldType;
use App\Enums\FlightReason;

/**
 * A flight. Airline and airports autocomplete from the 5,800 airlines and
 * 9,000 airports already in the database, so the form needs no third-party
 * API and works with no network beyond the site itself.
 *
 * Codes rather than names are stored (ICAO for the airline, IATA for the
 * airports), which is what the lookups return as their value.
 */
final class FlightFields
{
    /**
     * @return list<FieldData>
     */
    public static function fields(): array
    {
        return [
            FieldData::primary('occurred_at', 'Departs', FieldType::DateTime, required: true, defaultsToNow: true),
            FieldData::primary('origin_iata', 'From', FieldType::Lookup, 'Airport code.', required: true, source: 'airport'),
            FieldData::primary('destination_iata', 'To', FieldType::Lookup, 'Airport code.', required: true, source: 'airport'),
            FieldData::primary('airline_icao', 'Airline', FieldType::Lookup, source: 'airline'),
            FieldData::primary('flight_number', 'Flight number', FieldType::Text),
            FieldData::optional('cabin_class', 'Cabin', FieldType::Select, options: array_map(
                fn (CabinClass $class): array => ['value' => $class->value, 'label' => $class->label()],
                CabinClass::cases(),
            )),
            FieldData::optional('reason', 'Reason', FieldType::Select, options: array_map(
                fn (FlightReason $reason): array => ['value' => $reason->value, 'label' => $reason->label()],
                FlightReason::cases(),
            )),
            FieldData::optional('duration', 'Duration', FieldType::Duration),
            FieldData::optional('distance', 'Distance', FieldType::Distance),
            FieldData::optional('departure_timezone', 'Departure timezone', FieldType::Lookup, source: 'timezone'),
            FieldData::optional('arrival_timezone', 'Arrival timezone', FieldType::Lookup, source: 'timezone'),
        ];
    }
}
