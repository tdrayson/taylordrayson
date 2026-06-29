<?php

namespace App\Filament\Resources\FuelStations\Pages;

use App\Filament\Resources\FuelStations\FuelStationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFuelStation extends EditRecord
{
    protected static string $resource = FuelStationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
