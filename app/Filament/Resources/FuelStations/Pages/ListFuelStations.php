<?php

namespace App\Filament\Resources\FuelStations\Pages;

use App\Filament\Resources\FuelStations\FuelStationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFuelStations extends ListRecords
{
    protected static string $resource = FuelStationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
