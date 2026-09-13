<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MORPH_COLUMNS = [['timeline_entries', 'dataset'], ['attachments', 'model_type'], ['taggables', 'taggable_type']];

    public function up(): void
    {
        Schema::rename('calories', 'food');

        foreach (self::MORPH_COLUMNS as [$table, $column]) {
            DB::table($table)->where($column, 'calorie')->update([$column => 'food']);
        }
    }

    public function down(): void
    {
        foreach (self::MORPH_COLUMNS as [$table, $column]) {
            DB::table($table)->where($column, 'food')->update([$column => 'calorie']);
        }

        Schema::rename('food', 'calories');
    }
};
