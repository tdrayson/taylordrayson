<?php

namespace App\Filament\Resources\Calories\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CalorieForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DateTimePicker::make('occurred_at')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('icon'),
                TextInput::make('meal')
                    ->required(),
                TextInput::make('quantity')
                    ->required()
                    ->numeric(),
                TextInput::make('units')
                    ->required(),
                TextInput::make('calories')
                    ->required()
                    ->numeric(),
                TextInput::make('fat')
                    ->numeric(),
                TextInput::make('protein')
                    ->numeric(),
                TextInput::make('carbs')
                    ->numeric(),
                TextInput::make('saturated_fat')
                    ->numeric(),
                TextInput::make('sugars')
                    ->numeric(),
                TextInput::make('fibre')
                    ->numeric(),
                TextInput::make('cholesterol')
                    ->numeric(),
                TextInput::make('sodium')
                    ->numeric(),
            ]);
    }
}
