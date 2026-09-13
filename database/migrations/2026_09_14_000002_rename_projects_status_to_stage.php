<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** `status` becomes the publishing state on every table, so a project's lifecycle moves to `stage`. */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->renameColumn('status', 'stage');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->renameColumn('stage', 'status');
        });
    }
};
