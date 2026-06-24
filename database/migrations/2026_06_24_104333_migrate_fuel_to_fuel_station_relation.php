<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel', function (Blueprint $table) {
            $table->foreignId('fuel_station_id')
                ->nullable()
                ->after('odometer')
                ->constrained('fuel_stations')
                ->nullOnDelete();

            $table->dropColumn(['station', 'city']);
        });
    }

    public function down(): void
    {
        Schema::table('fuel', function (Blueprint $table) {
            $table->string('station')->nullable();
            $table->string('city')->nullable();
            $table->dropConstrainedForeignId('fuel_station_id');
        });
    }
};
