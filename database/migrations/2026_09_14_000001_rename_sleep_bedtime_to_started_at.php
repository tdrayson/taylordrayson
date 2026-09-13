<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sleep anchors on the moment it ended, so `wake_time` duplicates `occurred_at`
     * and `bedtime` becomes the span's `started_at`.
     */
    public function up(): void
    {
        $drift = DB::table('sleep')->whereColumn('wake_time', '!=', 'occurred_at')->count();

        if ($drift > 0) {
            throw new RuntimeException("{$drift} sleep rows have a wake_time that differs from occurred_at.");
        }

        Schema::table('sleep', function (Blueprint $table): void {
            $table->renameColumn('bedtime', 'started_at');
        });

        Schema::table('sleep', function (Blueprint $table): void {
            $table->dropColumn('wake_time');
        });
    }

    public function down(): void
    {
        Schema::table('sleep', function (Blueprint $table): void {
            $table->renameColumn('started_at', 'bedtime');
            $table->dateTime('wake_time')->nullable();
        });

        DB::table('sleep')->update(['wake_time' => DB::raw('occurred_at')]);

        Schema::table('sleep', function (Blueprint $table): void {
            $table->dateTime('wake_time')->nullable(false)->change();
        });
    }
};
