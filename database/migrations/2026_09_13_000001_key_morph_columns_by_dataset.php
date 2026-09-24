<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Morph columns hold the dataset key instead of a PHP class path, so renaming a
 * class never touches stored data again.
 */
return new class extends Migration
{
    /** Frozen at the time of this migration; later renames update keys themselves. */
    private const ALIASES = [
        'activity' => 'App\Models\Activity',
        'sleep' => 'App\Models\Sleep',
        'calorie' => 'App\Models\Calorie',
        'media' => 'App\Models\Media',
        'event' => 'App\Models\Event',
        'appearance' => 'App\Models\Appearance',
        'podcast' => 'App\Models\Podcast',
        'flight' => 'App\Models\Flight',
        'checkin' => 'App\Models\Checkin',
        'fuel' => 'App\Models\Fuel',
        'project' => 'App\Models\Project',
        'article' => 'App\Models\Article',
        'note' => 'App\Models\Note',
        'page' => 'App\Models\Page',
        'series' => 'App\Models\Series',
        'user' => 'App\Models\User',
        'trip' => 'App\Models\Trip',
    ];

    private const COLUMNS = [
        ['timeline_entries', 'dataset'],
        ['attachments', 'model_type'],
        ['taggables', 'taggable_type'],
        ['oauth_clients', 'owner_type'],
    ];

    public function up(): void
    {
        Schema::table('timeline_entries', function (Blueprint $table): void {
            $table->renameColumn('timelineable_type', 'dataset');
            $table->renameColumn('timelineable_id', 'entry_id');
        });

        foreach (self::COLUMNS as [$table, $column]) {
            foreach (self::ALIASES as $alias => $class) {
                DB::table($table)->where($column, $class)->update([$column => $alias]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as [$table, $column]) {
            foreach (self::ALIASES as $alias => $class) {
                DB::table($table)->where($column, $alias)->update([$column => $class]);
            }
        }

        Schema::table('timeline_entries', function (Blueprint $table): void {
            $table->renameColumn('dataset', 'timelineable_type');
            $table->renameColumn('entry_id', 'timelineable_id');
        });
    }
};
