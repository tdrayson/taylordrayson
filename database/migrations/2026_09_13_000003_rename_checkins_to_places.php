<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MORPH_COLUMNS = [['timeline_entries', 'dataset'], ['attachments', 'model_type'], ['taggables', 'taggable_type']];

    public function up(): void
    {
        Schema::rename('checkins', 'places');

        Schema::table('places', function (Blueprint $table): void {
            $table->renameColumn('category', 'type');
        });

        foreach (self::MORPH_COLUMNS as [$table, $column]) {
            DB::table($table)->where($column, 'checkin')->update([$column => 'place']);
        }
    }

    public function down(): void
    {
        foreach (self::MORPH_COLUMNS as [$table, $column]) {
            DB::table($table)->where($column, 'place')->update([$column => 'checkin']);
        }

        Schema::table('places', function (Blueprint $table): void {
            $table->renameColumn('type', 'category');
        });

        Schema::rename('places', 'checkins');
    }
};
