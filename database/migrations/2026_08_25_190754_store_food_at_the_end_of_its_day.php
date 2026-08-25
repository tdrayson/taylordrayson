<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Store a day's food at the end of that day rather than at midnight.
 *
 * Rovi gives a date and a meal label and no clock, so `occurred_at` was
 * midnight as a placeholder. Meanwhile the spine row was written at noon, so
 * food displayed 12:00am while sorting from midday, and its timezone resolved
 * from a moment before the day had happened: a flight at 08:16 left the whole
 * day's eating in the departure country.
 *
 * End of day is the moment a daily total is actually true.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('calories')->update([
            'occurred_at' => DB::raw("strftime('%Y-%m-%d 23:59:59', occurred_at)"),
        ]);

        // The spine follows, so ordering and display stop disagreeing. Instants
        // are left to `timezones:backfill`, which recomputes them from the zone.
        DB::table('timeline_entries')
            ->where('timelineable_type', 'App\Models\Calorie')
            ->update(['occurred_at' => DB::raw("strftime('%Y-%m-%d 23:59:59', occurred_at)")]);
    }

    public function down(): void
    {
        DB::table('calories')->update([
            'occurred_at' => DB::raw("strftime('%Y-%m-%d 00:00:00', occurred_at)"),
        ]);

        DB::table('timeline_entries')
            ->where('timelineable_type', 'App\Models\Calorie')
            ->update(['occurred_at' => DB::raw("strftime('%Y-%m-%d 12:00:00', occurred_at)")]);
    }
};
