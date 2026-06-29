<?php

namespace App\Filament\Resources\Airlines\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AirlineForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('iata_code'),
                TextInput::make('icao_code')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('country'),
            ]);
    }
}
