<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The name of the post a mention came from, kept apart from its content.
 *
 * A source's p-name was previously folded into `content` as a last resort,
 * which published somebody's article title inside e-content and told a parser
 * the title was what they wrote. The two are different claims and now have
 * different columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webmentions', function (Blueprint $table): void {
            $table->string('title')->nullable()->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('webmentions', function (Blueprint $table): void {
            $table->dropColumn('title');
        });
    }
};
