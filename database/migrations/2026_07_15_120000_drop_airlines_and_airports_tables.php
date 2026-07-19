<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Airlines and airports are static reference data, now served by Sushi
     * from database/lookups/*.csv. The app database keeps personal data only.
     */
    public function up(): void
    {
        Schema::dropIfExists('airlines');
        Schema::dropIfExists('airports');
    }

    /**
     * Recreated to match 2026_03_18_010000_create_app_tables.php. The rows are
     * not restored: the CSVs are the source of truth.
     */
    public function down(): void
    {
        Schema::create('airports', function (Blueprint $table) {
            $table->id();
            $table->string('iata_code')->unique();
            $table->string('icao_code')->nullable();
            $table->string('name');
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::create('airlines', function (Blueprint $table) {
            $table->id();
            $table->string('iata_code')->nullable();
            $table->string('icao_code')->unique();
            $table->string('name');
            $table->string('country')->nullable();
            $table->timestamps();
        });
    }
};
