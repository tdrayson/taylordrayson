<?php

namespace App\Filament\Resources\Media\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class MediaForm
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
                TextInput::make('rating')
                    ->numeric(),
                TextInput::make('platform_type'),
                TextInput::make('platform_id'),
                Textarea::make('meta')
                    ->columnSpanFull(),
            ]);
    }
}
