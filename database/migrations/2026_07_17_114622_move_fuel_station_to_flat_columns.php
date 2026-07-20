<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel', function (Blueprint $table): void {
            $table->string('station_name')->nullable()->after('vehicle_id');
            $table->string('brand')->nullable()->after('station_name');
            $table->string('address')->nullable()->after('brand');
            $table->string('postcode')->nullable()->after('address');
            $table->string('city')->nullable()->after('postcode');
            $table->string('county')->nullable()->after('city');
            $table->string('country')->nullable()->after('county');
            $table->decimal('latitude', 10, 7)->nullable()->after('country');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        Schema::table('fuel', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('fuel_station_id');
        });

        Schema::dropIfExists('fuel_stations');
    }

    public function down(): void
    {
        Schema::create('fuel_stations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('brand')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();

            $table->index(['name', 'city', 'country']);
        });

        Schema::table('fuel', function (Blueprint $table): void {
            $table->foreignId('fuel_station_id')
                ->nullable()
                ->after('odometer')
                ->constrained('fuel_stations')
                ->nullOnDelete();

            $table->dropColumn([
                'station_name', 'brand', 'address', 'postcode',
                'city', 'county', 'country', 'latitude', 'longitude',
            ]);
        });
    }
};
