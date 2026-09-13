<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function renameSleepMigration(): object
{
    return require database_path('migrations/2026_09_14_000001_rename_sleep_bedtime_to_started_at.php');
}

it('renames bedtime to started_at and drops wake_time', function () {
    $migration = renameSleepMigration();
    $migration->down();

    DB::table('sleep')->insert([
        'occurred_at' => '2026-06-20 07:00:00',
        'bedtime' => '2026-06-19 23:00:00',
        'wake_time' => '2026-06-20 07:00:00',
        'duration' => 28800,
    ]);

    $migration->up();

    expect(Schema::hasColumn('sleep', 'bedtime'))->toBeFalse()
        ->and(Schema::hasColumn('sleep', 'wake_time'))->toBeFalse()
        ->and(DB::table('sleep')->value('started_at'))->toBe('2026-06-19 23:00:00');
});

it('refuses to drop wake_time when it differs from occurred_at', function () {
    $migration = renameSleepMigration();
    $migration->down();

    DB::table('sleep')->insert([
        'occurred_at' => '2026-06-20 07:00:00',
        'bedtime' => '2026-06-19 23:00:00',
        'wake_time' => '2026-06-20 06:30:00',
        'duration' => 27000,
    ]);

    expect(fn () => $migration->up())->toThrow(RuntimeException::class);
});

it('restores wake_time from occurred_at on down', function () {
    DB::table('sleep')->insert([
        'occurred_at' => '2026-06-20 07:00:00',
        'started_at' => '2026-06-19 23:00:00',
        'duration' => 28800,
    ]);

    renameSleepMigration()->down();

    $row = DB::table('sleep')->first();

    expect($row->bedtime)->toBe('2026-06-19 23:00:00')
        ->and($row->wake_time)->toBe('2026-06-20 07:00:00');
});
