<?php

namespace App\Cp\Resources;

use App\Cp\TimelineCpResource;
use App\Models\Activity;

class ActivityResource extends TimelineCpResource
{
    public function model(): string
    {
        return Activity::class;
    }

    public function slug(): string
    {
        return 'activities';
    }

    public function label(): string
    {
        return 'Activity';
    }

    public function pluralLabel(): string
    {
        return 'Activities';
    }

    /** @return array<int, string> */
    public function searchable(): array
    {
        return ['name', 'type'];
    }

    /** @return array<int, array{key: string, label: string}> */
    public function columns(): array
    {
        return [
            ['key' => 'occurred_at', 'label' => 'Date'],
            ['key' => 'type', 'label' => 'Type'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'duration', 'label' => 'Duration'],
        ];
    }
}
