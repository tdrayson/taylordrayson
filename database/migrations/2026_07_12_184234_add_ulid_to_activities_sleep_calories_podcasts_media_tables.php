<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['activities', 'sleep', 'calories', 'podcasts', 'media'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->ulid('ulid')->nullable()->unique()->after('id');
            });
        }
    }

    public function down(): void
    {
        foreach (['activities', 'sleep', 'calories', 'podcasts', 'media'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('ulid');
            });
        }
    }
};
