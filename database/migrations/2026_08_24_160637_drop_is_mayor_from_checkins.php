<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the Foursquare mayorship flag. It was stored because the API returned
 * it, never because anything wanted it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkins', function (Blueprint $table): void {
            $table->dropColumn('is_mayor');
        });
    }

    public function down(): void
    {
        Schema::table('checkins', function (Blueprint $table): void {
            $table->boolean('is_mayor')->default(false);
        });
    }
};
