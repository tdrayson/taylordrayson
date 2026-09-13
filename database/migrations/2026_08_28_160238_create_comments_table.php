<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->morphs('commentable');

            // Self-referencing: the true chain is stored, but the UI flattens
            // it to one level, so a deep reply keeps its parent and renders
            // beside its siblings.
            $table->foreignId('parent_id')->nullable()->constrained('comments')->nullOnDelete();

            $table->string('author_name');

            // Optional, never displayed, and no part of spam control: it exists
            // only to notify someone that they got a reply.
            $table->string('author_email')->nullable();
            $table->boolean('notify_replies')->default(false);
            $table->timestamp('unsubscribed_at')->nullable();

            $table->text('body');
            $table->string('status')->default('pending');

            // Hashed rather than stored raw: it is only ever compared against
            // itself, to recognise a name that has commented acceptably before.
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();

            $table->index(['commentable_type', 'commentable_id', 'status']);
            $table->index(['author_name', 'ip_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
