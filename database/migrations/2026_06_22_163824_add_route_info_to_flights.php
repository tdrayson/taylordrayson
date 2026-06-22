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
        Schema::table('flights', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_min')->nullable()->after('distance_miles');
            $table->string('departure_timezone')->nullable()->after('duration_min');
            $table->string('arrival_timezone')->nullable()->after('departure_timezone');
            $table->unsignedInteger('co2_kg')->nullable()->after('arrival_timezone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flights', function (Blueprint $table) {
            $table->dropColumn(['duration_min', 'departure_timezone', 'arrival_timezone', 'co2_kg']);
        });
    }
};
