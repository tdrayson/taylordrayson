<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webmentions', function (Blueprint $table) {
            $table->id();

            // The pair the spec keys on. Unique, so a re-send updates the row
            // it already made rather than stacking duplicates, which is also
            // how an edit or a delete at the source is handled.
            $table->string('source_url');
            $table->string('target_url');

            // Resolved from target_url at receipt, so the conversation can be
            // queried per entry without parsing URLs again.
            $table->nullableMorphs('target');

            // Plain string, not a cast enum: the vocabulary belongs to other
            // people's sites, and casting an open set throws the moment an
            // unfamiliar value is read back.
            $table->string('kind')->default('mention');

            $table->string('author_name')->nullable();
            $table->string('author_url')->nullable();
            $table->string('author_photo_path')->nullable();
            $table->text('content')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->string('status')->default('pending');

            // verified_at records that the source really did link here;
            // last_checked_at drives re-checking a source that may have changed.
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();

            $table->timestamps();

            $table->unique(['source_url', 'target_url']);
            $table->index(['target_type', 'target_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webmentions');
    }
};
