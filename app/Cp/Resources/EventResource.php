<?php

namespace App\Cp\Resources;

use App\Cp\TimelineCpResource;
use App\Models\Event;

class EventResource extends TimelineCpResource
{
    public function model(): string
    {
        return Event::class;
    }

    public function slug(): string
    {
        return 'events';
    }

    public function label(): string
    {
        return 'Event';
    }

    public function pluralLabel(): string
    {
        return 'Events';
    }

    /** @return array<int, string> */
    public function searchable(): array
    {
        return ['name', 'venue_name', 'city'];
    }
}
