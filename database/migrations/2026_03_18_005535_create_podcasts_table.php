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
        Schema::create('podcasts', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at');
            $table->index('occurred_at');
            $table->integer('season_number');
            $table->integer('episode_number');
            $table->string('topic')->nullable();
            $table->text('show_notes')->nullable();
            $table->text('transcript')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->string('audio_url')->nullable();
            $table->string('youtube_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('podcasts');
    }
};
