<?php

namespace App\Filament\Resources\Sleeps\Pages;

use App\Filament\Resources\Sleeps\SleepResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSleep extends EditRecord
{
    protected static string $resource = SleepResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
