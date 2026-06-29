<?php

namespace App\Filament\Resources\Fuels;

use App\Filament\Resources\Fuels\Pages\CreateFuel;
use App\Filament\Resources\Fuels\Pages\EditFuel;
use App\Filament\Resources\Fuels\Pages\ListFuels;
use App\Filament\Resources\Fuels\Schemas\FuelForm;
use App\Filament\Resources\Fuels\Tables\FuelsTable;
use App\Models\Fuel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FuelResource extends Resource
{
    protected static ?string $model = Fuel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return FuelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FuelsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFuels::route('/'),
            'create' => CreateFuel::route('/create'),
            'edit' => EditFuel::route('/{record}/edit'),
        ];
    }
}
