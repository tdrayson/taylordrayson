<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_stations', function (Blueprint $table) {
            $table->string('brand')->nullable()->after('name');
            $table->string('address')->nullable()->after('brand');
        });
    }

    public function down(): void
    {
        Schema::table('fuel_stations', function (Blueprint $table) {
            $table->dropColumn(['brand', 'address']);
        });
    }
};
