<?php

namespace App\Filament\Resources\Appearances\Pages;

use App\Filament\Resources\Appearances\AppearanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAppearances extends ListRecords
{
    protected static string $resource = AppearanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
