<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The earlier migration only added timeline_entries.ends_at; existing
        // rows keep it NULL until re-saved. Backfill from the events table
        // (the source of truth) so multi-day display works on a fresh deploy
        // without waiting for every event to be re-imported. Idempotent: only
        // touches rows still NULL.
        DB::statement("
            UPDATE timeline_entries
            SET ends_at = (SELECT e.ends_at FROM events e WHERE e.id = timeline_entries.timelineable_id)
            WHERE timelineable_type = 'App\\Models\\Event' AND ends_at IS NULL
        ");
    }

    public function down(): void
    {
        // No-op: ends_at is re-derivable from events, nothing to reverse.
    }
};
