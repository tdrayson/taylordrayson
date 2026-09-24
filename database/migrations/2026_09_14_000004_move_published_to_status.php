<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['articles', 'pages'] as $name) {
            DB::table($name)->where('published', false)->update(['status' => 'draft']);
        }

        // The observer already removes a draft's spine row; this clears any a mass update left behind.
        $drafts = DB::table('articles')->where('status', 'draft')->pluck('id');
        DB::table('timeline_entries')->where('dataset', 'article')->whereIn('entry_id', $drafts)->delete();

        $stale = DB::table('timeline_entries')->where('dataset', 'article')->whereIn('entry_id', $drafts)->count();

        if ($stale > 0) {
            throw new RuntimeException("{$stale} draft articles still have timeline rows.");
        }

        foreach (['articles', 'pages'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn('published');
            });
        }
    }

    public function down(): void
    {
        foreach (['articles', 'pages'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->boolean('published')->default(false);
            });

            // Unlisted and private stay unpublished: the old code has no way to hide them from listings.
            DB::table($name)->where('status', 'published')->update(['published' => true]);
        }
    }
};
