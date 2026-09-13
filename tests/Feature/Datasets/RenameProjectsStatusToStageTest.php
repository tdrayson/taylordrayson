<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function renameProjectsStatusMigration(): object
{
    return require database_path('migrations/2026_09_14_000002_rename_projects_status_to_stage.php');
}

it('renames projects.status to stage, keeping every value', function () {
    $migration = renameProjectsStatusMigration();
    $migration->down();

    DB::table('projects')->insert([
        'occurred_at' => '2026-06-01 09:00:00',
        'title' => 'Hiuchi',
        'slug' => 'hiuchi',
        'status' => 'on_hold',
    ]);

    $migration->up();

    expect(Schema::hasColumn('projects', 'stage'))->toBeTrue()
        ->and(DB::table('projects')->value('stage'))->toBe('on_hold');
});

it('restores projects.status on down', function () {
    DB::table('projects')->insert([
        'occurred_at' => '2026-06-01 09:00:00',
        'title' => 'Hiuchi',
        'slug' => 'hiuchi',
        'stage' => 'archived',
    ]);

    renameProjectsStatusMigration()->down();

    expect(DB::table('projects')->value('status'))->toBe('archived');
});
