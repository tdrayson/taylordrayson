<?php

namespace App\Filament\Resources\Sleeps\Pages;

use App\Filament\Resources\Sleeps\SleepResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSleeps extends ListRecords
{
    protected static string $resource = SleepResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
