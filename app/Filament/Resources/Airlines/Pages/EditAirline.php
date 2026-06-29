<?php

namespace App\Filament\Resources\Airlines\Pages;

use App\Filament\Resources\Airlines\AirlineResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAirline extends EditRecord
{
    protected static string $resource = AirlineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
