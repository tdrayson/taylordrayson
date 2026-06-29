<?php

namespace App\Cp\Resources;

use App\Cp\TimelineCpResource;
use App\Models\Podcast;

class PodcastResource extends TimelineCpResource
{
    public function model(): string
    {
        return Podcast::class;
    }

    public function slug(): string
    {
        return 'this-week-with';
    }

    public function label(): string
    {
        return 'Episode';
    }

    public function pluralLabel(): string
    {
        return 'This Week With';
    }

    /** @return array<int, string> */
    public function searchable(): array
    {
        return ['topic'];
    }
}
