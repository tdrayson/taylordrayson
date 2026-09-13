<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Give events and checkins the same address parts fuel already stores.
 *
 * The place lookup returns street, postcode, city and country for every result,
 * but events had nowhere to put the first two and checkins nowhere for the
 * postcode, so they were fetched and dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('address')->nullable()->after('venue_name');
            $table->string('postcode')->nullable()->after('address');
        });

        Schema::table('checkins', function (Blueprint $table): void {
            $table->string('postcode')->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['address', 'postcode']);
        });

        Schema::table('checkins', function (Blueprint $table): void {
            $table->dropColumn('postcode');
        });
    }
};
