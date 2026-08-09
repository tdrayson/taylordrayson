<?php

use App\Models\Activity;
use App\Models\TimelineEntry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * A migrated but empty SQLite file to copy from, built by the app's own
 * migrations so its schema matches the target exactly.
 */
function sourceDatabase(): string
{
    $path = sys_get_temp_dir().'/db_copy_'.uniqid().'.sqlite';
    touch($path);

    config([
        'database.connections.copy_test_source' => [
            'driver' => 'sqlite',
            'database' => $path,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
    ]);

    Artisan::call('migrate', ['--database' => 'copy_test_source', '--force' => true]);

    return $path;
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/db_copy_*.sqlite') as $file) {
        unlink($file);
    }
});

it('copies rows across keeping their ids', function () {
    $path = sourceDatabase();
    $source = DB::connection('copy_test_source');

    // Ids are preserved deliberately: an attachment's files live in a folder
    // named after its id, so renumbering would strand every one of them.
    $source->table('activities')->insert([
        ['id' => 4200, 'occurred_at' => '2026-06-01 09:00:00', 'type' => 'run', 'name' => 'Copied Run', 'duration' => 1800],
        ['id' => 4201, 'occurred_at' => '2026-06-02 09:00:00', 'type' => 'walk', 'name' => 'Copied Walk', 'duration' => 900],
    ]);

    $this->artisan('db:copy', ['--from' => $path])->assertSuccessful();

    expect(Activity::count())->toBe(2)
        ->and(Activity::find(4200)?->name)->toBe('Copied Run')
        ->and(Activity::find(4201)?->name)->toBe('Copied Walk');
});

/**
 * The reason this is a command and not a SQL dump. SQLite writes a string
 * literal verbatim; MySQL reads a backslash inside one as an escape, so a
 * dumped 'App\Models\Activity' arrives as "AppModelsActivity" and every
 * polymorphic relation breaks, without one error to show for it. Going through
 * PDO leaves the escaping to the driver.
 */
it('keeps class names in polymorphic columns intact', function () {
    $path = sourceDatabase();
    $source = DB::connection('copy_test_source');

    $source->table('activities')->insert([
        'id' => 900, 'occurred_at' => '2026-06-01 09:00:00', 'type' => 'run', 'name' => 'Run', 'duration' => 600,
    ]);
    $source->table('timeline_entries')->insert([
        'id' => 900, 'timelineable_type' => Activity::class, 'timelineable_id' => 900, 'occurred_at' => '2026-06-01 09:00:00',
    ]);

    $this->artisan('db:copy', ['--from' => $path])->assertSuccessful();

    expect(DB::table('timeline_entries')->find(900)->timelineable_type)->toBe('App\Models\Activity')
        ->and(TimelineEntry::find(900)->timelineable)->not->toBeNull();
});

/**
 * The one failure that would otherwise leave no trace: an insert silently drops
 * values for columns the target does not have, so the copy reports success
 * having thrown data away.
 */
it('refuses to run when the two schemas disagree', function () {
    $path = sourceDatabase();
    DB::connection('copy_test_source')->statement('ALTER TABLE activities ADD COLUMN mood TEXT');

    $this->artisan('db:copy', ['--from' => $path])
        ->expectsOutputToContain('missing on the target')
        ->assertFailed();
});

it('leaves the target alone when only reporting', function () {
    $path = sourceDatabase();
    DB::connection('copy_test_source')->table('activities')->insert([
        'id' => 77, 'occurred_at' => '2026-06-01 09:00:00', 'type' => 'run', 'name' => 'Not Copied', 'duration' => 600,
    ]);

    $this->artisan('db:copy', ['--from' => $path, '--pretend' => true])->assertSuccessful();

    expect(Activity::count())->toBe(0);
});

it('fails rather than copying from a file that is not there', function () {
    $this->artisan('db:copy', ['--from' => '/tmp/nope.sqlite'])->assertFailed();
});
