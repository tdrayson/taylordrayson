<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Positive publication flag: `published` true means live, replacing the
     * negative `draft` boolean on the two content types that have states.
     */
    public function up(): void
    {
        foreach (['articles', 'pages'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->renameColumn('draft', 'published');
            });
            DB::table($table)->update(['published' => DB::raw('1 - published')]);
        }
    }

    public function down(): void
    {
        foreach (['articles', 'pages'] as $table) {
            DB::table($table)->update(['published' => DB::raw('1 - published')]);
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->renameColumn('published', 'draft');
            });
        }
    }
};
