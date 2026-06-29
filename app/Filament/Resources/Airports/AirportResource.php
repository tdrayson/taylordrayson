<?php

namespace App\Filament\Resources\Airports;

use App\Filament\Resources\Airports\Pages\CreateAirport;
use App\Filament\Resources\Airports\Pages\EditAirport;
use App\Filament\Resources\Airports\Pages\ListAirports;
use App\Filament\Resources\Airports\Schemas\AirportForm;
use App\Filament\Resources\Airports\Tables\AirportsTable;
use App\Models\Airport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AirportResource extends Resource
{
    protected static ?string $model = Airport::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|\UnitEnum|null $navigationGroup = 'Reference';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return AirportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AirportsTable::configure($table);
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
            'index' => ListAirports::route('/'),
            'create' => CreateAirport::route('/create'),
            'edit' => EditAirport::route('/{record}/edit'),
        ];
    }
}
