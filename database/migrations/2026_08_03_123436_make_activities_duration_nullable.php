<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Setgraph share can open an activity with no summary line, where the length is
 * genuinely unknown rather than zero. Strava fills it in when it adopts the row.
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
