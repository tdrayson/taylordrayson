<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Strava always states a moving time, so duration could be required while it
 * was the only thing creating activities. A Setgraph share can now open one
 * without a summary line, where the length is genuinely unknown rather than
 * zero, and Strava fills it in when it adopts the row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            $table->integer('duration')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            $table->integer('duration')->default(0)->nullable(false)->change();
        });
    }
};
