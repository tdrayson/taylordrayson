<?php

use App\Support\Links;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The sending site's host, stored rather than pattern-matched.
 *
 * Trust was decided with `author_url like %host%`, which matched on any
 * substring: a mention from indieweb.org made every later mention from
 * dieweb.org trusted too, and the scan could use no index. A host is what the
 * rule was always about, so it is now a column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webmentions', function (Blueprint $table) {
            $table->string('author_host')->nullable()->after('author_url');
            $table->index(['author_host', 'status']);
        });

        foreach (DB::table('webmentions')->whereNotNull('author_url')->get(['id', 'author_url']) as $row) {
            DB::table('webmentions')
                ->where('id', $row->id)
                ->update(['author_host' => Links::host($row->author_url)]);
        }
    }

    public function down(): void
    {
        Schema::table('webmentions', function (Blueprint $table) {
            $table->dropIndex(['author_host', 'status']);
            $table->dropColumn('author_host');
        });
    }
};
