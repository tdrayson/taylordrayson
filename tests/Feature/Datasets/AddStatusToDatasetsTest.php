<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function addStatusMigration(): object
{
    return require database_path('migrations/2026_09_14_000003_add_status_to_datasets.php');
}

it('backfills every existing row and spine row as published', function () {
    $migration = addStatusMigration();
    $migration->down();

    DB::table('notes')->insert(['id' => 1, 'occurred_at' => '2026-06-01 09:00:00', 'content' => '[]', 'slug' => 'hello']);
    DB::table('timeline_entries')->insert(['dataset' => 'note', 'entry_id' => 1, 'occurred_at' => '2026-06-01 09:00:00', 'url_slug' => 'hello']);

    $migration->up();

    expect(DB::table('notes')->value('status'))->toBe('published')
        ->and(DB::table('notes')->value('password'))->toBeNull()
        ->and(DB::table('timeline_entries')->value('status'))->toBe('published');
});

it('drops the columns again on down', function () {
    addStatusMigration()->down();

    expect(Schema::hasColumn('articles', 'status'))->toBeFalse()
        ->and(Schema::hasColumn('pages', 'password'))->toBeFalse()
        ->and(Schema::hasColumn('timeline_entries', 'status'))->toBeFalse();
});
