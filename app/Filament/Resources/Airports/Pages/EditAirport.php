<?php

namespace App\Filament\Resources\Airports\Pages;

use App\Filament\Resources\Airports\AirportResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAirport extends EditRecord
{
    protected static string $resource = AirportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
