<?php

namespace App\Filament\Resources\Projects\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DateTimePicker::make('occurred_at')
                    ->required(),
                TextInput::make('title')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                Textarea::make('long_description')
                    ->columnSpanFull(),
                TextInput::make('url')
                    ->url(),
                TextInput::make('github_url')
                    ->url(),
                TextInput::make('status')
                    ->required(),
                Toggle::make('featured')
                    ->required(),
                Textarea::make('tags')
                    ->columnSpanFull(),
                DateTimePicker::make('started_at'),
            ]);
    }
}
