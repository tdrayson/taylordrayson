<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every timeline type records the timezone the entry was captured in
     * (nullable; null renders as the home timezone). Notes, activities and
     * flights already carry theirs.
     *
     * @var list<string>
     */
    private array $tables = [
        'sleep',
        'calories',
        'media',
        'events',
        'appearances',
        'podcasts',
        'checkins',
        'projects',
        'fuel',
        'articles',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('timezone')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('timezone');
            });
        }
    }
};
