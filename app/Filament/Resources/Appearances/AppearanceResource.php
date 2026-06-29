<?php

namespace App\Filament\Resources\Appearances;

use App\Filament\Resources\Appearances\Pages\CreateAppearance;
use App\Filament\Resources\Appearances\Pages\EditAppearance;
use App\Filament\Resources\Appearances\Pages\ListAppearances;
use App\Filament\Resources\Appearances\Schemas\AppearanceForm;
use App\Filament\Resources\Appearances\Tables\AppearancesTable;
use App\Models\Appearance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AppearanceResource extends Resource
{
    protected static ?string $model = Appearance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return AppearanceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AppearancesTable::configure($table);
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
            'index' => ListAppearances::route('/'),
            'create' => CreateAppearance::route('/create'),
            'edit' => EditAppearance::route('/{record}/edit'),
        ];
    }
}
