<?php

namespace App\Filament\Resources\Podcasts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class PodcastForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DateTimePicker::make('occurred_at')
                    ->required(),
                TextInput::make('season_number')
                    ->required()
                    ->numeric(),
                TextInput::make('episode_number')
                    ->required()
                    ->numeric(),
                TextInput::make('topic'),
                Textarea::make('show_notes')
                    ->columnSpanFull(),
                Textarea::make('transcript')
                    ->columnSpanFull(),
                TextInput::make('duration')
                    ->numeric(),
                TextInput::make('audio_url')
                    ->url(),
                TextInput::make('video_url')
                    ->url(),
                TextInput::make('thumbnail'),
                FileUpload::make('cover_image')
                    ->image(),
            ]);
    }
}
