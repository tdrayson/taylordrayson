<?php

namespace App\Filament\Resources\Calories;

use App\Filament\Resources\Calories\Pages\CreateCalorie;
use App\Filament\Resources\Calories\Pages\EditCalorie;
use App\Filament\Resources\Calories\Pages\ListCalories;
use App\Filament\Resources\Calories\Schemas\CalorieForm;
use App\Filament\Resources\Calories\Tables\CaloriesTable;
use App\Models\Calorie;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class CalorieResource extends Resource
{
    protected static ?string $model = Calorie::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cake';

    protected static string|\UnitEnum|null $navigationGroup = 'Timeline';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Food';

    protected static ?string $pluralModelLabel = 'Food';

    public static function form(Schema $schema): Schema
    {
        return CalorieForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CaloriesTable::configure($table);
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
            'index' => ListCalories::route('/'),
            'create' => CreateCalorie::route('/create'),
            'edit' => EditCalorie::route('/{record}/edit'),
        ];
    }
}
