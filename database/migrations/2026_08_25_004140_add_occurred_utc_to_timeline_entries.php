<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The instant an entry happened, for ordering.
     *
     * `occurred_at` is local wall-clock, so ordering by it sorts by what the
     * clock said rather than when things happened: 09:00 in London and 09:00
     * in New York tie, five hours apart. Derived from occurred_at + timezone
     * and maintained alongside them, in the same spirit as url_slug.
     *
     * Grouping stays on the local date, so a day still reads as one heading.
     */
    public function up(): void
    {
        Schema::table('timeline_entries', function (Blueprint $table): void {
            $table->timestamp('occurred_utc')->nullable()->after('occurred_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('timeline_entries', function (Blueprint $table): void {
            $table->dropIndex(['occurred_utc']);
            $table->dropColumn('occurred_utc');
        });
    }
};
