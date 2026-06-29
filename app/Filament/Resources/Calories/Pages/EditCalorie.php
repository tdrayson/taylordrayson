<?php

namespace App\Filament\Resources\Calories\Pages;

use App\Filament\Resources\Calories\CalorieResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCalorie extends EditRecord
{
    protected static string $resource = CalorieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
