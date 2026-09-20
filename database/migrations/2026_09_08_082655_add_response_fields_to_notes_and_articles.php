<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a post is responding to, on the two types you write by hand.
 *
 * Columns rather than the polymorphic table the comments, reactions and
 * webmentions use: those are one-to-many, this is one-to-one, and a morph table
 * would put a join on the timeline query.
 */
return new class extends Migration
{
    private const TABLES = ['notes', 'articles'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('response_kind')->nullable()->after('content');
                $table->string('response_url', 500)->nullable()->after('response_kind');

                // Only an RSVP has an answer to give, so this is null on
                // everything else rather than a fourth table for one column.
                $table->string('rsvp_value')->nullable()->after('response_url');

                $table->index('response_kind');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['response_kind']);
                $table->dropColumn(['response_kind', 'response_url', 'rsvp_value']);
            });
        }
    }
};
