<?php

namespace App\Filament\Resources\Calories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CaloriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('icon')
                    ->searchable(),
                TextColumn::make('meal')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('units')
                    ->searchable(),
                TextColumn::make('calories')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('fat')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('protein')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('carbs')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('saturated_fat')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('sugars')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('fibre')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('cholesterol')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('sodium')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
