<?php

use App\Models\TimelineEntry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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

        $entries = TimelineEntry::query()
            ->with('timelineable')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->cursor();

        foreach ($entries as $entry) {
            $model = $entry->timelineable;

            if ($model === null) {
                continue;
            }

            $day = $entry->occurred_at->toDateString();
            $base = $model->slug();

            $candidate = $base;
            $suffix = 1;

            while (isset($taken[$day][$candidate])) {
                $suffix++;
                $candidate = "{$base}-{$suffix}";
            }

            $taken[$day][$candidate] = true;

            TimelineEntry::query()->whereKey($entry->id)->update(['url_slug' => $candidate]);
        }
    }

    public function down(): void
    {
        Schema::table('timeline_entries', function (Blueprint $table) {
            $table->dropColumn('url_slug');
        });
    }
};
