<?php

namespace App\Cp\Resources;

use App\Cp\TimelineCpResource;
use App\Models\Checkin;

class CheckinResource extends TimelineCpResource
{
    public function model(): string
    {
        return Checkin::class;
    }

    public function slug(): string
    {
        return 'places';
    }

    public function label(): string
    {
        return 'Place';
    }

    public function pluralLabel(): string
    {
        return 'Places';
    }

    /** @return array<int, string> */
    public function searchable(): array
    {
        return ['venue_name', 'category', 'city'];
    }
}
