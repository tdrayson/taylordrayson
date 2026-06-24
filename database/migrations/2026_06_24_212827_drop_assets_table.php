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
        Schema::dropIfExists('assets');
    }

    /**
     * Reverse the migrations: recreate the original custom assets table that
     * Media Library's `attachments` table replaced.
     */
    public function down(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->morphs('assetable');
            $table->string('type');
            $table->string('path');
            $table->string('original_filename')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
    }
};
