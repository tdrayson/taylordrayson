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
            $table->unsignedInteger('duration')->nullable()->after('distance_miles');
            $table->string('departure_timezone')->nullable()->after('duration');
            $table->string('arrival_timezone')->nullable()->after('departure_timezone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flights', function (Blueprint $table) {
            $table->dropColumn(['duration', 'departure_timezone', 'arrival_timezone']);
        });
    }
};
