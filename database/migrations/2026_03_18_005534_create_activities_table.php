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
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at');
            $table->index('occurred_at');
            $table->string('type');
            $table->string('name')->nullable();
            $table->integer('duration_seconds');
            $table->integer('calories')->nullable();
            $table->decimal('distance_km', 8, 3)->nullable();
            $table->text('polyline')->nullable();
            $table->json('heart_rate')->nullable();
            $table->string('platform_type')->nullable();
            $table->string('platform_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['platform_type', 'platform_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
