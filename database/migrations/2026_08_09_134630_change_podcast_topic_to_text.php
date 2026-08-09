<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An episode topic is a sentence, not a title: 31 of the 255 stored are
     * already longer than the varchar(255) the column was declared as, the
     * longest 387 characters.
     *
     * SQLite does not enforce a varchar length, so they stored fine and nothing
     * ever complained. MySQL does, and would have truncated or refused every
     * one of them. show_notes beside it is already text.
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
