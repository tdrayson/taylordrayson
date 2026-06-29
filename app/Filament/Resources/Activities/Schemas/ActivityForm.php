<?php

namespace App\Filament\Resources\Activities\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ActivityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DateTimePicker::make('occurred_at')
                    ->required(),
                TextInput::make('type')
                    ->required(),
                TextInput::make('name'),
                TextInput::make('duration')
                    ->required()
                    ->numeric(),
                TextInput::make('calories')
                    ->numeric(),
                TextInput::make('distance_km')
                    ->numeric(),
                TextInput::make('average_heart_rate')
                    ->numeric(),
                TextInput::make('max_heart_rate')
                    ->numeric(),
                Textarea::make('heart_rate')
                    ->columnSpanFull(),
                TextInput::make('platform_type'),
                TextInput::make('platform_id'),
                Textarea::make('meta')
                    ->columnSpanFull(),
                TextInput::make('timezone'),
            ]);
    }
}
