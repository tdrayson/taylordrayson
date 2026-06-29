<?php

namespace App\Filament\Resources\Sleeps\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class SleepForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DateTimePicker::make('occurred_at')
                    ->required(),
                DateTimePicker::make('bedtime')
                    ->required(),
                DateTimePicker::make('wake_time')
                    ->required(),
                TextInput::make('duration')
                    ->required()
                    ->numeric(),
                TextInput::make('awake')
                    ->numeric(),
                TextInput::make('rem')
                    ->numeric(),
                TextInput::make('core')
                    ->numeric(),
                TextInput::make('deep')
                    ->numeric(),
                TextInput::make('source'),
                Textarea::make('stages')
                    ->columnSpanFull(),
                TextInput::make('score')
                    ->numeric(),
                TextInput::make('duration_score')
                    ->numeric(),
                TextInput::make('bedtime_score')
                    ->numeric(),
                TextInput::make('interruption_score')
                    ->numeric(),
            ]);
    }
}
