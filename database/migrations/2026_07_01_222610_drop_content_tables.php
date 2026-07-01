<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the content tables that have been superseded by Statamic flat-files.
     * Timeline entry rows for the old morph types are deleted first to avoid
     * orphaned rows and foreign-key constraint issues.
     */
    public function up(): void
    {
        // Remove any residual timeline_entries rows for the removed morph types.
        DB::table('timeline_entries')
            ->whereIn('timelineable_type', ['App\\Models\\Article', 'App\\Models\\Note'])
            ->delete();

        Schema::dropIfExists('articles');
        Schema::dropIfExists('notes');
        Schema::dropIfExists('pages');
    }

    /**
     * Recreate the tables from the original migration schemas for reversibility.
     */
    public function down(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at');
            $table->index('occurred_at');
            $table->string('title');
            $table->string('slug');
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->boolean('draft')->default(false);
            $table->json('tags')->nullable();
            $table->timestamps();
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at');
            $table->index('occurred_at');
            $table->text('content');
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('excerpt')->nullable();
            $table->json('content')->nullable();
            $table->boolean('draft')->default(false);
            $table->timestamps();
        });
    }
};
