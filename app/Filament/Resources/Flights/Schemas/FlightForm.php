<?php

namespace App\Filament\Resources\Flights\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class FlightForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DateTimePicker::make('occurred_at')
                    ->required(),
                TextInput::make('flight_number')
                    ->required(),
                TextInput::make('airline_icao'),
                TextInput::make('origin_iata')
                    ->required(),
                TextInput::make('destination_iata')
                    ->required(),
                TextInput::make('distance_miles')
                    ->numeric(),
                TextInput::make('cabin_class'),
                TextInput::make('reason'),
                Textarea::make('meta')
                    ->columnSpanFull(),
                TextInput::make('duration')
                    ->numeric(),
                TextInput::make('departure_timezone'),
                TextInput::make('arrival_timezone'),
            ]);
    }
}
