<?php

namespace App\Filament\Resources\Appearances\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class AppearanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DateTimePicker::make('occurred_at')
                    ->required(),
                TextInput::make('type')
                    ->required(),
                TextInput::make('title')
                    ->required(),
                TextInput::make('show_name')
                    ->required(),
                TextInput::make('url')
                    ->url(),
                TextInput::make('video_url')
                    ->url(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('duration')
                    ->numeric(),
                TextInput::make('audio_url')
                    ->url(),
            ]);
    }
}
