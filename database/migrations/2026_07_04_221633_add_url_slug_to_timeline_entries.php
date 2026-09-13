<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each spine row stores the entry's URL slug, assigned once at write time
     * (bare slug for the first same-day entry, -2/-3... for collisions) so
     * URLs never reshuffle when earlier entries are backfilled or deleted.
     */
    public function up(): void
    {
        Schema::table('timeline_entries', function (Blueprint $table) {
            $table->string('url_slug')->nullable()->index();
        });

        $taken = [];

        // Reads the raw morph columns via the query builder rather than the
        // TimelineEntry model: at this point in history `timelineable_type`
        // still holds the model's class path, not the dataset key the later
        // `entry()` relation resolves through.
        $rows = DB::table('timeline_entries')
            ->select('id', 'occurred_at', 'timelineable_type', 'timelineable_id')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->cursor();

        foreach ($rows as $row) {
            if ($row->timelineable_type === null || $row->timelineable_id === null || ! class_exists($row->timelineable_type)) {
                continue;
            }

            $model = $row->timelineable_type::find($row->timelineable_id);

            if ($model === null) {
                continue;
            }

            $day = Carbon::parse($row->occurred_at)->toDateString();
            $base = $model->slug();

            $candidate = $base;
            $suffix = 1;

            while (isset($taken[$day][$candidate])) {
                $suffix++;
                $candidate = "{$base}-{$suffix}";
            }

            $taken[$day][$candidate] = true;

            DB::table('timeline_entries')->where('id', $row->id)->update(['url_slug' => $candidate]);
        }
    }

    public function down(): void
    {
        Schema::table('timeline_entries', function (Blueprint $table) {
            $table->dropColumn('url_slug');
        });
    }
};
