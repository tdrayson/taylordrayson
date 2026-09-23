<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('syndicated_responses', function (Blueprint $table): void {
            // My own reply on Strava or Swarm, so it is shown as mine rather
            // than as somebody responding to me.
            $table->boolean('mine')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('syndicated_responses', function (Blueprint $table): void {
            $table->dropColumn('mine');
        });
    }
};
