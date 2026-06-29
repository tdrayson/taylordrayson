<?php

namespace App\Filament\Resources\Calories\Pages;

use App\Filament\Resources\Calories\CalorieResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCalories extends ListRecords
{
    protected static string $resource = CalorieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
