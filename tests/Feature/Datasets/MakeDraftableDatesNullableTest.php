<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function nullableDatesMigration(): object
{
    return require database_path('migrations/2026_09_14_000005_make_draftable_dates_nullable.php');
}

it('stores an undated draft and a book started_at', function () {
    DB::table('notes')->insert(['occurred_at' => null, 'content' => '[]', 'status' => 'draft']);
    DB::table('books')->insert(['occurred_at' => null, 'title' => 'Dune', 'status' => 'draft', 'started_at' => '2026-06-01 09:00:00']);

    expect(DB::table('notes')->whereNull('occurred_at')->count())->toBe(1)
        ->and(DB::table('books')->value('started_at'))->toBe('2026-06-01 09:00:00');
});

it('dates undated rows from their last edit on down, and drops started_at', function () {
    DB::table('notes')->insert(['occurred_at' => null, 'content' => '[]', 'status' => 'draft', 'updated_at' => '2026-06-03 10:00:00']);

    $migration = nullableDatesMigration();
    $migration->down();

    expect(DB::table('notes')->value('occurred_at'))->toBe('2026-06-03 10:00:00')
        ->and(Schema::hasColumn('books', 'started_at'))->toBeFalse();

    $migration->up();

    expect(Schema::hasColumn('books', 'started_at'))->toBeTrue();
});
