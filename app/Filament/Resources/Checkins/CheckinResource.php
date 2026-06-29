<?php

namespace App\Filament\Resources\Checkins;

use App\Filament\Resources\Checkins\Pages\CreateCheckin;
use App\Filament\Resources\Checkins\Pages\EditCheckin;
use App\Filament\Resources\Checkins\Pages\ListCheckins;
use App\Filament\Resources\Checkins\Schemas\CheckinForm;
use App\Filament\Resources\Checkins\Tables\CheckinsTable;
use App\Models\Checkin;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class CheckinResource extends Resource
{
    protected static ?string $model = Checkin::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static string|\UnitEnum|null $navigationGroup = 'Timeline';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Places';

    protected static ?string $pluralModelLabel = 'Places';

    public static function form(Schema $schema): Schema
    {
        return CheckinForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CheckinsTable::configure($table);
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
            'index' => ListCheckins::route('/'),
            'create' => CreateCheckin::route('/create'),
            'edit' => EditCheckin::route('/{record}/edit'),
        ];
    }
}
