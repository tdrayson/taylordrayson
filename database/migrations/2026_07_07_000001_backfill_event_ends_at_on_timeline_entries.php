<?php

use App\Models\Event;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The class name is bound rather than inlined: MySQL reads a backslash in
        // a string literal as an escape, so 'App\Models\Event' would arrive as
        // "AppModelsEvent" and silently match no rows.
        DB::statement('
            UPDATE timeline_entries
            SET ends_at = (SELECT e.ends_at FROM events e WHERE e.id = timeline_entries.timelineable_id)
            WHERE timelineable_type = ? AND ends_at IS NULL
        ', [Event::class]);
    }

    public function down(): void
    {
        // No-op: ends_at is re-derivable from events, nothing to reverse.
    }
};
