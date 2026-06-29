<?php

namespace App\Filament\Resources\Appearances\Pages;

use App\Filament\Resources\Appearances\AppearanceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAppearance extends EditRecord
{
    protected static string $resource = AppearanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
