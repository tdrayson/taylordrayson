<?php

namespace App\Filament\Resources\Fuels\Pages;

use App\Filament\Resources\Fuels\FuelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFuel extends EditRecord
{
    protected static string $resource = FuelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
