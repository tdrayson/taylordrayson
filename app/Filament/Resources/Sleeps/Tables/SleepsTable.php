<?php

namespace App\Filament\Resources\Sleeps\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SleepsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('bedtime')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('wake_time')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('duration')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('awake')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rem')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('core')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('deep')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('source')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('score')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('duration_score')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('bedtime_score')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('interruption_score')
                    ->numeric()
                    ->sortable(),
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
