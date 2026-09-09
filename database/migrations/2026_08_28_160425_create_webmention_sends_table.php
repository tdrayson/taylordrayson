<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webmention_sends', function (Blueprint $table) {
            $table->id();

            // Which of our entries the link was found in, so a re-save can ask
            // where it sent last time.
            $table->nullableMorphs('source');

            $table->string('source_url');
            $table->string('target_url');

            // Rediscovered on every send, per the spec: a target's endpoint can
            // move, so a stored one is a hint rather than a fact.
            $table->string('endpoint')->nullable();

            $table->string('status')->default('pending');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);

            // The body hash at the last successful send. A trivial re-save
            // leaves it unchanged and sends nothing; a real edit changes it and
            // the spec's "re-send on update" applies.
            $table->string('content_hash', 64)->nullable();

            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            // nullableMorphs() already indexes (source_type, source_id).
            $table->unique(['source_url', 'target_url']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webmention_sends');
    }
};
