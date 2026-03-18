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
        Schema::create('sleep', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at');
            $table->index('occurred_at');
            $table->timestamp('bedtime');
            $table->timestamp('wake_time');
            $table->integer('duration_minutes');
            $table->integer('awake_minutes')->nullable();
            $table->integer('rem_minutes')->nullable();
            $table->integer('core_minutes')->nullable();
            $table->integer('deep_minutes')->nullable();
            $table->json('stages')->nullable();
            $table->string('source')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sleep');
    }
};
