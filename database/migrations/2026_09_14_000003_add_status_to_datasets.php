<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Listed by hand rather than read from Datasets, so a later dataset change cannot rewrite history. */
    private const TABLES = [
        'activities', 'sleep', 'food', 'films', 'tv_episodes', 'books', 'events', 'appearances',
        'this_week_with', 'flights', 'places', 'fuel', 'projects', 'articles', 'notes', 'pages',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('status')->default('published')->index();
                $table->string('password')->nullable();
            });
        }

        Schema::table('timeline_entries', function (Blueprint $table): void {
            $table->string('status')->default('published')->index();
        });
    }

    public function down(): void
    {
        foreach ([...self::TABLES, 'timeline_entries'] as $name) {
            // SQLite refuses to drop a column an index still names.
            Schema::table($name, function (Blueprint $table): void {
                $table->dropIndex(['status']);
            });

            Schema::table($name, function (Blueprint $table) use ($name): void {
                $table->dropColumn($name === 'timeline_entries' ? ['status'] : ['status', 'password']);
            });
        }
    }
};
