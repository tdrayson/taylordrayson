<?php

namespace App\Cp\Resources;

use App\Cp\CpResource;
use App\Models\Airport;

class AirportResource extends CpResource
{
    public function model(): string
    {
        return Airport::class;
    }

    public function slug(): string
    {
        return 'airports';
    }

    public function label(): string
    {
        return 'Airport';
    }

    public function pluralLabel(): string
    {
        return 'Airports';
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
        return ['name', 'iata_code', 'city'];
    }
}
