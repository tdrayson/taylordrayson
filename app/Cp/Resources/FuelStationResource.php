<?php

namespace App\Cp\Resources;

use App\Cp\CpResource;
use App\Models\FuelStation;

class FuelStationResource extends CpResource
{
    public function model(): string
    {
        return FuelStation::class;
    }

    public function slug(): string
    {
        return 'fuel-stations';
    }

    public function label(): string
    {
        return 'Fuel station';
    }

    public function pluralLabel(): string
    {
        return 'Fuel stations';
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
        return ['name', 'city'];
    }
}
