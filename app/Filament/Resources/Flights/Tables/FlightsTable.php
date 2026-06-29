<?php

namespace App\Filament\Resources\Flights\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FlightsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('flight_number')
                    ->searchable(),
                TextColumn::make('airline_icao')
                    ->searchable(),
                TextColumn::make('origin_iata')
                    ->searchable(),
                TextColumn::make('destination_iata')
                    ->searchable(),
                TextColumn::make('distance_miles')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('cabin_class')
                    ->searchable(),
                TextColumn::make('reason')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('duration')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('departure_timezone')
                    ->searchable(),
                TextColumn::make('arrival_timezone')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
