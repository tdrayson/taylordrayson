<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TV episodes are the timeline dataset; TV shows are a separate page that
 * gathers them. Renames both tables, the FK between them, and the morph keys
 * that spell out the old names.
 */
return new class extends Migration
{
    private const MORPH_COLUMNS = [['timeline_entries', 'dataset'], ['attachments', 'model_type'], ['taggables', 'taggable_type']];

    public function up(): void
    {
        // Dropped by column while the table still carries the name it was
        // created under ('episodes'), so Laravel's conventional FK name
        // resolves; re-added under the new table/column names below rather
        // than relying on a rename to carry an old-named constraint forward.
        Schema::table('episodes', function (Blueprint $table): void {
            $table->dropForeign(['series_id']);
        });

        Schema::rename('series', 'tv_shows');
        Schema::rename('episodes', 'tv_episodes');

        Schema::table('tv_episodes', function (Blueprint $table): void {
            $table->renameColumn('series_id', 'tv_show_id');
        });

        Schema::table('tv_episodes', function (Blueprint $table): void {
            $table->foreign('tv_show_id')->references('id')->on('tv_shows')->nullOnDelete();
        });

        foreach (self::MORPH_COLUMNS as [$table, $column]) {
            DB::table($table)->where($column, 'episode')->update([$column => 'tv-episode']);
        }

        DB::table('attachments')->where('model_type', 'series')->update(['model_type' => 'tv-show']);
    }

    public function down(): void
    {
        DB::table('attachments')->where('model_type', 'tv-show')->update(['model_type' => 'series']);

        foreach (self::MORPH_COLUMNS as [$table, $column]) {
            DB::table($table)->where($column, 'tv-episode')->update([$column => 'episode']);
        }

        Schema::table('tv_episodes', function (Blueprint $table): void {
            $table->dropForeign(['tv_show_id']);
        });

        Schema::table('tv_episodes', function (Blueprint $table): void {
            $table->renameColumn('tv_show_id', 'series_id');
        });

        Schema::rename('tv_episodes', 'episodes');
        Schema::rename('tv_shows', 'series');

        Schema::table('episodes', function (Blueprint $table): void {
            $table->foreign('series_id')->references('id')->on('series')->nullOnDelete();
        });
    }
};
