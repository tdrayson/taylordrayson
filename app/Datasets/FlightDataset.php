<?php

namespace App\Datasets;

use App\Enums\DatasetKind;
use App\Enums\TimelineType;
use App\Models\Flight;
use App\Presenters\Cards\FlightCard;
use App\Timeline\Taxonomies;

/**
 * Flights taken, from manually logged trips.
 */
final class FlightDataset extends BaseDataset
{
    public function type(): TimelineType
    {
        return TimelineType::Flight;
    }

    public function model(): string
    {
        return Flight::class;
    }

    public function kind(): DatasetKind
    {
        return DatasetKind::Travel;
    }

    public function icon(): string
    {
        return 'AirplaneTakeOff01Icon';
    }

    public function label(): string
    {
        return 'Flight';
    }

    public function plural(): string
    {
        return 'Flights';
    }

    public function slug(): string
    {
        return 'flights';
    }

    public function keywords(): string
    {
        return 'fly travel trip airport';
    }

    public function card(): FlightCard
    {
        return new FlightCard;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function searchFields(): array
    {
        return [
            'airline' => ['label' => 'Airline', 'dataType' => 'text', 'relation' => 'airline', 'column' => 'name', 'category' => 'Flight'],
            'number' => ['label' => 'Flight number', 'dataType' => 'text', 'column' => 'flight_number', 'category' => 'Flight'],
            'cabin' => ['label' => 'Cabin class', 'dataType' => 'enum', 'column' => 'cabin_class', 'category' => 'Flight'],
            'reason' => ['label' => 'Reason', 'dataType' => 'text', 'column' => 'reason', 'category' => 'Flight'],
            'origin' => ['label' => 'Origin (IATA)', 'dataType' => 'text', 'column' => 'origin_iata', 'category' => 'Route'],
            'destination' => ['label' => 'Destination (IATA)', 'dataType' => 'text', 'column' => 'destination_iata', 'category' => 'Route'],
            'distance' => ['label' => 'Distance', 'dataType' => 'number', 'column' => 'distance', 'category' => 'Route', 'measure' => 'distance', 'store' => 'm'],
            'flight_duration' => ['label' => 'Duration', 'dataType' => 'duration', 'column' => 'duration', 'category' => 'Route'],
        ];
    }

    /**
     * @return list<string>
     */
    public function textColumns(): array
    {
        return ['flight_number', 'origin_iata', 'destination_iata', 'reason'];
    }

    public function taxonomy(): callable
    {
        return Taxonomies::airline();
    }
}
