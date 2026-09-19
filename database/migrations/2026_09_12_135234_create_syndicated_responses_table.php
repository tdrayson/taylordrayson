<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syndicated_responses', function (Blueprint $table) {
            $table->id();

            $table->nullableMorphs('target');

            // Matches the source/source_id pair on activities and checkins: the
            // service, and its own id for this response. A kudo has no id of
            // its own, which is why the second is nullable.
            $table->string('source');
            $table->string('source_id')->nullable();

            // What this replies to, in the source's own namespace. Mastodon
            // gives one; Strava comments are flat.
            $table->string('parent_source_id')->nullable();

            $table->string('kind');
            $table->string('emoji')->nullable();

            $table->string('author_name');
            $table->string('author_photo_path')->nullable();
            $table->string('author_photo_url')->nullable();

            $table->json('body')->nullable();

            // The response's own address where it has one, otherwise the post
            // it sits on. Swarm has neither worth linking.
            $table->string('url')->nullable();

            $table->timestamp('occurred_at');
            $table->string('status')->default('approved');
            $table->timestamps();

            $table->unique(['source', 'source_id']);
            $table->index(['target_type', 'target_id', 'status']);
            $table->index(['target_type', 'target_id', 'source', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syndicated_responses');
    }
};
