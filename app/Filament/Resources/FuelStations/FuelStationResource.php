<?php

namespace App\Filament\Resources\FuelStations;

use App\Filament\Resources\FuelStations\Pages\CreateFuelStation;
use App\Filament\Resources\FuelStations\Pages\EditFuelStation;
use App\Filament\Resources\FuelStations\Pages\ListFuelStations;
use App\Filament\Resources\FuelStations\Schemas\FuelStationForm;
use App\Filament\Resources\FuelStations\Tables\FuelStationsTable;
use App\Models\FuelStation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FuelStationResource extends Resource
{
    protected static ?string $model = FuelStation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return FuelStationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FuelStationsTable::configure($table);
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
            'index' => ListFuelStations::route('/'),
            'create' => CreateFuelStation::route('/create'),
            'edit' => EditFuelStation::route('/{record}/edit'),
        ];
    }
}
