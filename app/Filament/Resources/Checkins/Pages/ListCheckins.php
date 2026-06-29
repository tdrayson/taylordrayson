<?php

namespace App\Filament\Resources\Checkins\Pages;

use App\Filament\Resources\Checkins\CheckinResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCheckins extends ListRecords
{
    protected static string $resource = CheckinResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
