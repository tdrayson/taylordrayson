<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function movePublishedMigration(): object
{
    return require database_path('migrations/2026_09_14_000004_move_published_to_status.php');
}

it('turns published articles and pages into published, the rest into drafts', function () {
    $migration = movePublishedMigration();
    $migration->down();

    DB::table('articles')->insert([
        ['id' => 1, 'occurred_at' => '2026-06-01 09:00:00', 'title' => 'Live', 'slug' => 'live', 'content' => '[]', 'published' => true],
        ['id' => 2, 'occurred_at' => '2026-06-02 09:00:00', 'title' => 'Unfinished', 'slug' => 'unfinished', 'content' => '[]', 'published' => false],
    ]);
    DB::table('pages')->insert([
        ['title' => 'About', 'slug' => 'about', 'published' => true],
        ['title' => 'Colophon', 'slug' => 'colophon', 'published' => false],
    ]);
    DB::table('timeline_entries')->insert(['dataset' => 'article', 'entry_id' => 2, 'occurred_at' => '2026-06-02 09:00:00', 'url_slug' => 'unfinished']);

    $migration->up();

    expect(DB::table('articles')->orderBy('id')->pluck('status')->all())->toBe(['published', 'draft'])
        ->and(DB::table('pages')->orderBy('slug')->pluck('status', 'slug')->all())->toBe(['about' => 'published', 'colophon' => 'draft'])
        ->and(Schema::hasColumn('articles', 'published'))->toBeFalse()
        ->and(Schema::hasColumn('pages', 'published'))->toBeFalse()
        ->and(DB::table('timeline_entries')->where('dataset', 'article')->where('entry_id', 2)->exists())->toBeFalse();
});

it('restores the published flags on down, publishing only what was published', function () {
    DB::table('articles')->insert([
        ['id' => 1, 'occurred_at' => '2026-06-01 09:00:00', 'title' => 'A', 'slug' => 'a', 'content' => '[]', 'status' => 'published'],
        ['id' => 2, 'occurred_at' => '2026-06-01 09:00:00', 'title' => 'B', 'slug' => 'b', 'content' => '[]', 'status' => 'unlisted'],
        ['id' => 3, 'occurred_at' => '2026-06-01 09:00:00', 'title' => 'C', 'slug' => 'c', 'content' => '[]', 'status' => 'draft'],
    ]);

    movePublishedMigration()->down();

    expect(DB::table('articles')->orderBy('id')->pluck('published')->map(fn ($value): bool => (bool) $value)->all())
        ->toBe([true, false, false]);
});
