<?php

namespace App\Filament\Resources\Notes;

use App\Filament\Resources\Notes\Pages\CreateNote;
use App\Filament\Resources\Notes\Pages\EditNote;
use App\Filament\Resources\Notes\Pages\ListNotes;
use App\Filament\Resources\Notes\Schemas\NoteForm;
use App\Filament\Resources\Notes\Tables\NotesTable;
use App\Models\Note;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class NoteResource extends Resource
{
    protected static ?string $model = Note::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return NoteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NotesTable::configure($table);
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
            'index' => ListNotes::route('/'),
            'create' => CreateNote::route('/create'),
            'edit' => EditNote::route('/{record}/edit'),
        ];
    }
}
