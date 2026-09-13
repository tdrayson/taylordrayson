<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MORPH_COLUMNS = [['timeline_entries', 'dataset'], ['attachments', 'model_type'], ['taggables', 'taggable_type']];

    public function up(): void
    {
        Schema::rename('podcasts', 'this_week_with');

        foreach (self::MORPH_COLUMNS as [$table, $column]) {
            DB::table($table)->where($column, 'podcast')->update([$column => 'this-week-with']);
        }
    }

    public function down(): void
    {
        foreach (self::MORPH_COLUMNS as [$table, $column]) {
            DB::table($table)->where($column, 'this-week-with')->update([$column => 'podcast']);
        }

        Schema::rename('this_week_with', 'podcasts');
    }
};
