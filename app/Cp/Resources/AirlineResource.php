<?php

namespace App\Cp\Resources;

use App\Cp\CpResource;
use App\Models\Airline;

class AirlineResource extends CpResource
{
    public function model(): string
    {
        return Airline::class;
    }

    public function slug(): string
    {
        return 'airlines';
    }

    public function label(): string
    {
        return 'Airline';
    }

    public function pluralLabel(): string
    {
        return 'Airlines';
    }

    public function group(): string
    {
        return 'Reference';
    }

    /** @return array{0: string, 1: string} */
    public function defaultSort(): array
    {
        return ['name', 'asc'];
    }

    /** @return array<int, string> */
    public function searchable(): array
    {
        return ['name', 'iata_code', 'icao_code'];
    }
}
