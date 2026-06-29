<?php

namespace App\Filament\Resources\Podcasts\Pages;

use App\Filament\Resources\Podcasts\PodcastResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPodcast extends EditRecord
{
    protected static string $resource = PodcastResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
