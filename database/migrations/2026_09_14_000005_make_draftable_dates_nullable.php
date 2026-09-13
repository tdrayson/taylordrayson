<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DRAFTABLE = ['notes', 'articles', 'projects', 'events', 'books', 'flights', 'fuel', 'appearances'];

    /** A draft has no date until it leaves draft; a book being read knows when it started. */
    public function up(): void
    {
        foreach (self::DRAFTABLE as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dateTime('occurred_at')->nullable()->change();
            });
        }

        Schema::table('books', function (Blueprint $table): void {
            $table->dateTime('started_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->dropColumn('started_at');
        });

        foreach (self::DRAFTABLE as $name) {
            DB::table($name)->whereNull('occurred_at')
                ->update(['occurred_at' => DB::raw('COALESCE(updated_at, created_at, CURRENT_TIMESTAMP)')]);

            Schema::table($name, function (Blueprint $table): void {
                $table->dateTime('occurred_at')->nullable(false)->change();
            });
        }
    }
};
