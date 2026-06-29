<?php

namespace App\Filament\Resources\Podcasts\Pages;

use App\Filament\Resources\Podcasts\PodcastResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePodcast extends CreateRecord
{
    protected static string $resource = PodcastResource::class;
}
