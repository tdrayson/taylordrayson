<?php

namespace App\Cp\Resources;

use App\Cp\TimelineCpResource;
use App\Models\Calorie;

class CalorieResource extends TimelineCpResource
{
    public function model(): string
    {
        return Calorie::class;
    }

    public function slug(): string
    {
        return 'food';
    }

    public function label(): string
    {
        return 'Food entry';
    }

    public function pluralLabel(): string
    {
        return 'Food';
    }

    /** @return array<int, string> */
    public function searchable(): array
    {
        return ['name', 'meal'];
    }
}
