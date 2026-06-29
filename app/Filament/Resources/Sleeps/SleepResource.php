<?php

namespace App\Filament\Resources\Sleeps;

use App\Filament\Resources\Sleeps\Pages\CreateSleep;
use App\Filament\Resources\Sleeps\Pages\EditSleep;
use App\Filament\Resources\Sleeps\Pages\ListSleeps;
use App\Filament\Resources\Sleeps\Schemas\SleepForm;
use App\Filament\Resources\Sleeps\Tables\SleepsTable;
use App\Models\Sleep;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SleepResource extends Resource
{
    protected static ?string $model = Sleep::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return SleepForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SleepsTable::configure($table);
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
            'index' => ListSleeps::route('/'),
            'create' => CreateSleep::route('/create'),
            'edit' => EditSleep::route('/{record}/edit'),
        ];
    }
}
