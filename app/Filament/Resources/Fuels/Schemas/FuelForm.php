<?php

namespace App\Filament\Resources\Fuels\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FuelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DateTimePicker::make('occurred_at')
                    ->required(),
                TextInput::make('vehicle_id')
                    ->required(),
                TextInput::make('litres')
                    ->required()
                    ->numeric(),
                TextInput::make('cost')
                    ->required()
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('fuel_card_cost')
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('price_per_litre')
                    ->numeric(),
                TextInput::make('odometer')
                    ->numeric(),
                Select::make('fuel_station_id')
                    ->relationship('fuelStation', 'name'),
            ]);
    }
}
