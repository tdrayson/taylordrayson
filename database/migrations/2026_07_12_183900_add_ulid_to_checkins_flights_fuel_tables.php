<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkins', function (Blueprint $table) {
            $table->ulid('ulid')->nullable()->unique()->after('id');
        });

        Schema::table('flights', function (Blueprint $table) {
            $table->ulid('ulid')->nullable()->unique()->after('id');
        });

        Schema::table('fuel', function (Blueprint $table) {
            $table->ulid('ulid')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('checkins', function (Blueprint $table) {
            $table->dropColumn('ulid');
        });

        Schema::table('flights', function (Blueprint $table) {
            $table->dropColumn('ulid');
        });

        Schema::table('fuel', function (Blueprint $table) {
            $table->dropColumn('ulid');
        });
    }
};
