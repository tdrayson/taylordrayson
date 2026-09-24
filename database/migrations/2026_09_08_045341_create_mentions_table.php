<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentions', function (Blueprint $table) {
            $table->id();

            // Both ends are polymorphic because a page is a legitimate source
            // and a legitimate target: it has a Portable Text body to link
            // from, and it takes interactions like any entry.
            $table->morphs('source');
            $table->morphs('target');

            // No updated_at: a mention is written or deleted, never edited.
            $table->timestamp('created_at')->nullable();

            // Linking to the same entry three times in one note is one mention.
            // The unique pair is what lets the sync be a plain upsert.
            $table->unique(
                ['source_type', 'source_id', 'target_type', 'target_id'],
                'mentions_pair_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentions');
    }
};
