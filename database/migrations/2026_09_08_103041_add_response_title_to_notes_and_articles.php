<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the post responded to is called, fetched from the page itself.
 *
 * Cached per post rather than in a shared table keyed by URL: a response has
 * exactly one target, two posts answering the same URL is rare, and a column
 * beside the URL it describes needs no lookup to read.
 */
return new class extends Migration
{
    private const TABLES = ['notes', 'articles'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('response_title')->nullable()->after('response_url');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('response_title');
            });
        }
    }
};
