<?php

namespace App\Cp\Resources;

use App\Cp\TimelineCpResource;
use App\Models\Fuel;

class FuelResource extends TimelineCpResource
{
    public function model(): string
    {
        return Fuel::class;
    }

    public function slug(): string
    {
        return 'fuel';
    }

    public function label(): string
    {
        return 'Fuel entry';
    }

    public function pluralLabel(): string
    {
        return 'Fuel';
    }
}
