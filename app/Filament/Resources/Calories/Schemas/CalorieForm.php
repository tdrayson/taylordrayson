<?php

namespace App\Filament\Resources\Calories\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CalorieForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Item')
                    ->description('What you ate, and when.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->columnSpanFull(),
                        DatePicker::make('occurred_at')
                            ->label('Date')
                            ->native(false)
                            ->displayFormat('j M Y')
                            ->required(),
                        Select::make('meal')
                            ->options([
                                'breakfast' => 'Breakfast',
                                'lunch' => 'Lunch',
                                'dinner' => 'Dinner',
                                'snacks' => 'Snacks',
                            ])
                            ->native(false)
                            ->required(),
                        TextInput::make('quantity')
                            ->numeric()
                            ->required(),
                        TextInput::make('units')
                            ->datalist(['Serving', 'Grams', 'Millilitres', 'Each', 'Piece'])
                            ->required(),
                        TextInput::make('icon')
                            ->helperText('Icon key used on the timeline.'),
                    ]),
                Section::make('Nutrition')
                    ->description('Totals for the logged quantity.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('calories')->numeric()->required()->suffix('kcal'),
                        TextInput::make('fat')->numeric()->suffix('g'),
                        TextInput::make('saturated_fat')->numeric()->suffix('g'),
                        TextInput::make('protein')->numeric()->suffix('g'),
                        TextInput::make('carbs')->numeric()->suffix('g'),
                        TextInput::make('sugars')->numeric()->suffix('g'),
                        TextInput::make('fibre')->numeric()->suffix('g'),
                        TextInput::make('sodium')->numeric()->suffix('mg'),
                        TextInput::make('cholesterol')->numeric()->suffix('mg'),
                    ]),
            ]);
    }
}
