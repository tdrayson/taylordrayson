<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timeline_entries', function (Blueprint $table): void {
            $table->timestamp('ends_at')->nullable()->after('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::table('timeline_entries', function (Blueprint $table): void {
            $table->dropColumn('ends_at');
        });
    }
};
