<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citations', function (Blueprint $table) {
            $table->id();
            $table->string('url', 500)->unique();
            $table->string('site');
            $table->string('title')->nullable();
            $table->string('author_name')->nullable();
            $table->string('author_photo_path')->nullable();
            $table->string('author_photo_url', 500)->nullable();
            $table->text('excerpt')->nullable();
            $table->timestamp('published_at')->nullable();
            // The author's own offset, kept so the date reads as they wrote it.
            $table->string('published_timezone')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
        });

        foreach (['notes', 'articles'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('citation_id')->nullable()->constrained()->nullOnDelete();
                $table->text('response_quote')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['notes', 'articles'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('citation_id');
                $table->dropColumn('response_quote');
            });
        }

        Schema::dropIfExists('citations');
    }
};
