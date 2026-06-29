<?php

namespace App\Filament\Resources\Airports\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AirportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('iata_code')
                    ->required(),
                TextInput::make('icao_code'),
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
