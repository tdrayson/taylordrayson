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
        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            // JSON-encoded, so an int comes back an int and an object an array
            // without a companion type column to keep in sync.
            $table->text('value');
            // When the value was true in the world, which is not necessarily
            // when it was written: a phone can send a reading minutes late.
            $table->dateTime('observed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('states');
    }
};
