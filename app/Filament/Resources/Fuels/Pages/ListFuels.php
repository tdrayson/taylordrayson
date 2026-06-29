<?php

namespace App\Filament\Resources\Fuels\Pages;

use App\Filament\Resources\Fuels\FuelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFuels extends ListRecords
{
    protected static string $resource = FuelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
