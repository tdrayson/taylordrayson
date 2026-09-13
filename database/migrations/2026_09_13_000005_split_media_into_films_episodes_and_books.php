<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Split media into one table per dataset, keeping ids so timeline entries and
 * attachments only need their stored dataset key changed.
 */
return new class extends Migration
{
    private const TABLES = ['film' => 'films', 'episode' => 'episodes', 'book' => 'books'];

    private const MORPH_COLUMNS = [['timeline_entries', 'dataset', 'entry_id'], ['attachments', 'model_type', 'model_id'], ['taggables', 'taggable_type', 'taggable_id']];

    public function up(): void
    {
        $unknown = DB::table('media')->whereNotIn('type', array_keys(self::TABLES))->count();

        if ($unknown > 0) {
            throw new RuntimeException("media has {$unknown} row(s) with a type outside film/episode/book; refusing to split.");
        }

        foreach (self::TABLES as $type => $table) {
            Schema::create($table, function (Blueprint $blueprint) use ($type): void {
                $blueprint->id();

                if ($type === 'episode') {
                    $blueprint->foreignId('series_id')->nullable()->constrained('series')->nullOnDelete();
                }

                $blueprint->timestamp('occurred_at');
                $blueprint->index('occurred_at');
                $blueprint->string('title');
                $blueprint->integer('rating')->nullable();
                $blueprint->string('source')->nullable();
                $blueprint->string('source_id')->nullable();
                $blueprint->json('meta')->nullable();
                $blueprint->timestamps();
                $blueprint->string('timezone')->nullable();
                $blueprint->unique(['source', 'source_id']);
            });
        }

        foreach (self::TABLES as $type => $table) {
            $columns = 'id, occurred_at, timezone, title, rating, source, source_id, meta, created_at, updated_at';
            $episodeColumns = $type === 'episode' ? ', series_id' : '';

            DB::statement("INSERT INTO {$table} ({$columns}{$episodeColumns}) SELECT {$columns}{$episodeColumns} FROM media WHERE type = ?", [$type]);

            foreach (self::MORPH_COLUMNS as [$morphTable, $typeColumn, $idColumn]) {
                DB::table($morphTable)
                    ->where($typeColumn, 'media')
                    ->whereIn($idColumn, DB::table('media')->select('id')->where('type', $type))
                    ->update([$typeColumn => $type]);
            }
        }

        Schema::drop('media');
    }

    public function down(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('series_id')->nullable()->after('id')->constrained('series')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->index('occurred_at');
            $table->string('type');
            $table->string('title');
            $table->integer('rating')->nullable();
            $table->string('source')->nullable();
            $table->string('source_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->string('timezone')->nullable();
            $table->unique(['source', 'source_id']);
        });

        foreach (self::TABLES as $type => $table) {
            $columns = 'id, occurred_at, timezone, title, rating, source, source_id, meta, created_at, updated_at';
            $episodeColumns = $type === 'episode' ? ', series_id' : '';
            $selectColumns = $type === 'episode' ? $columns.$episodeColumns : $columns.', NULL as series_id';

            DB::statement("INSERT INTO media ({$columns}, series_id, type) SELECT {$selectColumns}, ? FROM {$table}", [$type]);

            foreach (self::MORPH_COLUMNS as [$morphTable, $typeColumn, $idColumn]) {
                DB::table($morphTable)
                    ->where($typeColumn, $type)
                    ->whereIn($idColumn, DB::table($table)->select('id'))
                    ->update([$typeColumn => 'media']);
            }
        }

        foreach (self::TABLES as $table) {
            Schema::drop($table);
        }
    }
};
