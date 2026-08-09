<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ROUND() with no CAST: MySQL spells the integer cast SIGNED and SQLite
     * spells it INTEGER, and neither is needed. The column becomes an integer
     * in a later migration, which converts the already-whole value for us.
     */
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->renameColumn('distance_km', 'distance');
        });
        DB::table('activities')->whereNotNull('distance')->update([
            'distance' => DB::raw('ROUND(distance * 1000)'),
        ]);

        Schema::table('flights', function (Blueprint $table) {
            $table->renameColumn('distance_miles', 'distance');
        });
        DB::table('flights')->whereNotNull('distance')->update([
            'distance' => DB::raw('ROUND(distance * 1609.344)'),
        ]);
    }

    public function down(): void
    {
        DB::table('activities')->whereNotNull('distance')->update([
            'distance' => DB::raw('distance / 1000.0'),
        ]);
        Schema::table('activities', function (Blueprint $table) {
            $table->renameColumn('distance', 'distance_km');
        });

        DB::table('flights')->whereNotNull('distance')->update([
            'distance' => DB::raw('ROUND(distance / 1609.344)'),
        ]);
        Schema::table('flights', function (Blueprint $table) {
            $table->renameColumn('distance', 'distance_miles');
        });
    }
};
