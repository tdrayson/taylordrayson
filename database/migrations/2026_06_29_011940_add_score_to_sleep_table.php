<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sleep', function (Blueprint $table) {
            $table->unsignedTinyInteger('score')->nullable()->after('stages');
            $table->unsignedTinyInteger('duration_score')->nullable()->after('score');
            $table->unsignedTinyInteger('bedtime_score')->nullable()->after('duration_score');
            $table->unsignedTinyInteger('interruption_score')->nullable()->after('bedtime_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sleep', function (Blueprint $table) {
            $table->dropColumn(['score', 'duration_score', 'bedtime_score', 'interruption_score']);
        });
    }
};
