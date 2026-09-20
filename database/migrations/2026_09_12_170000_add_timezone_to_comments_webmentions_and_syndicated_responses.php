<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The author's own timezone, so a response can render on Aaron Parecki's
 * convention: the time its author saw, offset shown, rather than converted
 * into the entry's clock.
 *
 * An IANA name or a fixed offset ("+05:30"), Carbon::setTimezone accepts both.
 * Null means no author timezone is known, and rendering falls back to the
 * entry's own timezone as it already does. Syndicated responses carry no such
 * column: neither Strava nor Swarm ever reports an author timezone, so there
 * is nothing for it to hold.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->string('timezone')->nullable()->after('user_agent');
        });

        Schema::table('webmentions', function (Blueprint $table): void {
            $table->string('timezone')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->dropColumn('timezone');
        });

        Schema::table('webmentions', function (Blueprint $table): void {
            $table->dropColumn('timezone');
        });
    }
};
