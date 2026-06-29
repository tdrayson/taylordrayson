<?php

namespace App\Filament\Resources\Checkins\Pages;

use App\Filament\Resources\Checkins\CheckinResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCheckin extends CreateRecord
{
    protected static string $resource = CheckinResource::class;
}
