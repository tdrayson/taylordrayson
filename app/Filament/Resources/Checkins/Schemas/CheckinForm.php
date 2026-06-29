<?php

namespace App\Filament\Resources\Checkins\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CheckinForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DateTimePicker::make('occurred_at')
                    ->required(),
                TextInput::make('venue_name')
                    ->required(),
                TextInput::make('category'),
                TextInput::make('address'),
                TextInput::make('city'),
                TextInput::make('county'),
                TextInput::make('country'),
                TextInput::make('latitude')
                    ->numeric(),
                TextInput::make('longitude')
                    ->numeric(),
                Textarea::make('description')
                    ->columnSpanFull(),
                Toggle::make('is_mayor')
                    ->required(),
                TextInput::make('platform_type'),
                TextInput::make('platform_id'),
            ]);
    }
}
