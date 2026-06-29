<?php

namespace App\Filament\Resources\FuelStations\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FuelStationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('city'),
                TextInput::make('country'),
                TextInput::make('latitude')
                    ->numeric(),
                TextInput::make('longitude')
                    ->numeric(),
            ]);
    }
}
