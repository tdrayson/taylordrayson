<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appearances', function (Blueprint $table) {
            $table->ulid('ulid')->nullable()->unique()->after('id');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->ulid('ulid')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('appearances', function (Blueprint $table) {
            $table->dropColumn('ulid');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('ulid');
        });
    }
};
