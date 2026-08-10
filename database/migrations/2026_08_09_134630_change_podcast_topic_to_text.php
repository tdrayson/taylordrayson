<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An episode topic is a sentence, not a title, and many stored ones exceed
     * varchar(255). SQLite does not enforce the length so nothing complained;
     * MySQL would truncate or refuse them.
     */
    public function up(): void
    {
        Schema::table('podcasts', function (Blueprint $table): void {
            $table->text('topic')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('podcasts', function (Blueprint $table): void {
            $table->string('topic')->nullable()->change();
        });
    }
};
