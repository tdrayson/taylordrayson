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
        foreach (['activities', 'checkins', 'media'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropUnique(['platform_type', 'platform_id']);
                $table->renameColumn('platform_type', 'source');
                $table->renameColumn('platform_id', 'source_id');
            });

            Schema::table($table, function (Blueprint $table): void {
                $table->unique(['source', 'source_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['activities', 'checkins', 'media'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropUnique(['source', 'source_id']);
                $table->renameColumn('source', 'platform_type');
                $table->renameColumn('source_id', 'platform_id');
            });

            Schema::table($table, function (Blueprint $table): void {
                $table->unique(['platform_type', 'platform_id']);
            });
        }
    }
};
