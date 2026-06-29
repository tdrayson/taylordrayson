<?php

namespace App\Filament\Resources\Calories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CaloriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Date')
                    ->date('j M Y')
                    ->sortable(),
                TextColumn::make('name')
                    ->weight(FontWeight::Medium)
                    ->searchable(),
                TextColumn::make('meal')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'breakfast' => 'warning',
                        'lunch' => 'success',
                        'dinner' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('calories')
                    ->numeric()
                    ->suffix(' kcal')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('units')
                    ->toggleable(),
                TextColumn::make('protein')->numeric()->suffix(' g')->alignEnd()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('carbs')->numeric()->suffix(' g')->alignEnd()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('fat')->numeric()->suffix(' g')->alignEnd()->sortable()->toggleable(isToggledHiddenByDefault: true),
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
