<?php

namespace App\Filament\Resources\Airlines;

use App\Filament\Resources\Airlines\Pages\CreateAirline;
use App\Filament\Resources\Airlines\Pages\EditAirline;
use App\Filament\Resources\Airlines\Pages\ListAirlines;
use App\Filament\Resources\Airlines\Schemas\AirlineForm;
use App\Filament\Resources\Airlines\Tables\AirlinesTable;
use App\Models\Airline;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AirlineResource extends Resource
{
    protected static ?string $model = Airline::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return AirlineForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AirlinesTable::configure($table);
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
            'index' => ListAirlines::route('/'),
            'create' => CreateAirline::route('/create'),
            'edit' => EditAirline::route('/{record}/edit'),
        ];
    }
}
