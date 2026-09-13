<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** The migration under test, already applied by RefreshDatabase; replayed here in isolation. */
function tvRenameMigration(): object
{
    return require database_path('migrations/2026_09_13_000008_rename_episodes_and_series_to_tv.php');
}

it('renames the tables, keeps the FK, and rewrites the morph keys on up', function () {
    $migration = tvRenameMigration();
    $migration->down();

    DB::table('series')->insert(['id' => 1, 'trakt_id' => 1, 'slug' => 'severance', 'title' => 'Severance', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('episodes')->insert(['id' => 1, 'series_id' => 1, 'occurred_at' => now(), 'title' => 'Good News', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('timeline_entries')->insert(['dataset' => 'episode', 'entry_id' => 1, 'url_slug' => 'good-news', 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('attachments')->insert(['model_type' => 'series', 'model_id' => 1, 'uuid' => (string) Str::uuid(), 'collection_name' => 'cover', 'name' => 'x', 'file_name' => 'x.webp', 'disk' => 'public', 'size' => 1, 'manipulations' => '[]', 'custom_properties' => '[]', 'generated_conversions' => '[]', 'responsive_images' => '[]']);

    $migration->up();

    expect(Schema::hasTable('tv_shows'))->toBeTrue()
        ->and(Schema::hasTable('tv_episodes'))->toBeTrue()
        ->and(Schema::hasTable('series'))->toBeFalse()
        ->and(Schema::hasTable('episodes'))->toBeFalse()
        ->and(Schema::hasColumn('tv_episodes', 'tv_show_id'))->toBeTrue()
        ->and(DB::table('tv_episodes')->where('id', 1)->value('tv_show_id'))->toBe(1)
        ->and(DB::table('timeline_entries')->where('entry_id', 1)->value('dataset'))->toBe('tv-episode')
        ->and(DB::table('attachments')->where('model_id', 1)->value('model_type'))->toBe('tv-show');

    $foreignKey = collect(Schema::getForeignKeys('tv_episodes'))->firstWhere('foreign_table', 'tv_shows');

    expect($foreignKey)->not->toBeNull()
        ->and($foreignKey['columns'])->toBe(['tv_show_id']);
});

it('reverses the rename, the FK and the morph keys on down', function () {
    $migration = tvRenameMigration();
    $migration->down();

    expect(Schema::hasTable('series'))->toBeTrue()
        ->and(Schema::hasTable('episodes'))->toBeTrue()
        ->and(Schema::hasTable('tv_shows'))->toBeFalse()
        ->and(Schema::hasTable('tv_episodes'))->toBeFalse()
        ->and(Schema::hasColumn('episodes', 'series_id'))->toBeTrue();

    $foreignKey = collect(Schema::getForeignKeys('episodes'))->firstWhere('foreign_table', 'series');

    expect($foreignKey)->not->toBeNull()
        ->and($foreignKey['columns'])->toBe(['series_id']);

    DB::table('series')->insert(['id' => 1, 'trakt_id' => 1, 'slug' => 'severance', 'title' => 'Severance', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('episodes')->insert(['id' => 1, 'series_id' => 1, 'occurred_at' => now(), 'title' => 'Good News', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('timeline_entries')->insert(['dataset' => 'tv-episode', 'entry_id' => 1, 'url_slug' => 'good-news', 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('attachments')->insert(['model_type' => 'tv-show', 'model_id' => 1, 'uuid' => (string) Str::uuid(), 'collection_name' => 'cover', 'name' => 'x', 'file_name' => 'x.webp', 'disk' => 'public', 'size' => 1, 'manipulations' => '[]', 'custom_properties' => '[]', 'generated_conversions' => '[]', 'responsive_images' => '[]']);

    // A second down() call, from the state a real rollback would run it in
    // after up() has already run once more, reverses freshly-written rows too.
    $migration->up();
    $migration->down();

    expect(DB::table('timeline_entries')->where('entry_id', 1)->value('dataset'))->toBe('episode')
        ->and(DB::table('attachments')->where('model_id', 1)->value('model_type'))->toBe('series');
});
