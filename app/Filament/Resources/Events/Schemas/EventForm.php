<?php

namespace App\Filament\Resources\Events\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DateTimePicker::make('occurred_at')
                    ->required(),
                TextInput::make('type')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('venue_name'),
                TextInput::make('address'),
                TextInput::make('city'),
                TextInput::make('country'),
                TextInput::make('latitude')
                    ->numeric(),
                TextInput::make('longitude')
                    ->numeric(),
                TextInput::make('ticket_price')
                    ->numeric()
                    ->prefix('$'),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
