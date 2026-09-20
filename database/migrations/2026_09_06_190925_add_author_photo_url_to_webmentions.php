<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remember where an author's photo came from, not just where we put it.
 *
 * The stored path is a hash of the source URL, which is one-way: the file could
 * never be re-fetched once written, so a lost avatars directory was permanent
 * and a changed avatar was invisible. Keeping the URL makes both recoverable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webmentions', function (Blueprint $table) {
            $table->string('author_photo_url')->nullable()->after('author_photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('webmentions', function (Blueprint $table) {
            $table->dropColumn('author_photo_url');
        });
    }
};
