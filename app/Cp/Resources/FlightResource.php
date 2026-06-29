<?php

namespace App\Cp\Resources;

use App\Cp\TimelineCpResource;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Flight;

class FlightResource extends TimelineCpResource
{
    public function model(): string
    {
        return Flight::class;
    }

    public function slug(): string
    {
        return 'flights';
    }

    public function label(): string
    {
        return 'Flight';
    }

    public function pluralLabel(): string
    {
        return 'Flights';
    }

    /** @return array<int, string> */
    public function searchable(): array
    {
        return ['flight_number', 'origin_iata', 'destination_iata', 'reason'];
    }

    /** @return array<int, array{key: string, label: string}> */
    public function columns(): array
    {
        return [
            ['key' => 'occurred_at', 'label' => 'Date'],
            ['key' => 'flight_number', 'label' => 'Flight'],
            ['key' => 'origin_iata', 'label' => 'From'],
            ['key' => 'destination_iata', 'label' => 'To'],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function fieldOverrides(): array
    {
        return [
            'airline_icao' => [
                'type' => 'relation',
                'label' => 'Airline',
                'source' => Airline::class,
                'valueKey' => 'icao_code',
                'labelKey' => 'name',
                'searchable' => ['name', 'icao_code', 'iata_code'],
            ],
            'origin_iata' => [
                'type' => 'relation',
                'label' => 'From',
                'source' => Airport::class,
                'valueKey' => 'iata_code',
                'labelKey' => 'name',
                'searchable' => ['name', 'iata_code', 'city'],
            ],
            'destination_iata' => [
                'type' => 'relation',
                'label' => 'To',
                'source' => Airport::class,
                'valueKey' => 'iata_code',
                'labelKey' => 'name',
                'searchable' => ['name', 'iata_code', 'city'],
            ],
            'cabin_class' => ['type' => 'select', 'options' => [
                ['value' => 'economy', 'label' => 'Economy'],
                ['value' => 'premium-economy', 'label' => 'Premium economy'],
                ['value' => 'business', 'label' => 'Business'],
                ['value' => 'first', 'label' => 'First'],
            ]],
        ];
    }
}
