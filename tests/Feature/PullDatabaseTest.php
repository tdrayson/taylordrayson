<?php

use Illuminate\Support\Facades\Storage;

/*
 * The restore path is only worth testing end to end: a dump that unpacks but
 * rebuilds into an unreadable file is the failure that matters, and only
 * running sqlite3 over it proves otherwise.
 */

beforeEach(function () {
    Storage::fake('r2');
    config()->set('filesystems.disks.r2.bucket', 'backups');
    config()->set('backup.backup.name', 'database');

    $this->workspace = storage_path('app/pull-test-'.bin2hex(random_bytes(4)));
    mkdir($this->workspace, 0755, true);

    $this->target = $this->workspace.'/local.sqlite';
    config()->set('database.connections.sqlite.database', $this->target);
});

afterEach(function () {
    foreach (glob($this->workspace.'/*') ?: [] as $path) {
        unlink($path);
    }

    rmdir($this->workspace);
});

/** A real archive shaped the way spatie writes one: a SQL dump under db-dumps/. */
function fakeBackup(string $sql): string
{
    $path = tempnam(sys_get_temp_dir(), 'bk').'.zip';

    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('db-dumps/database.sql', $sql);
    $zip->close();

    return $path;
}

it('rebuilds the local database from the newest backup', function () {
    Storage::disk('r2')->put('database/2026-08-01-03-00-00.zip', file_get_contents(fakeBackup(
        'CREATE TABLE notes (id INTEGER PRIMARY KEY, body TEXT); INSERT INTO notes VALUES (1, "old");'
    )));
    Storage::disk('r2')->put('database/2026-08-25-03-00-00.zip', file_get_contents(fakeBackup(
        'CREATE TABLE notes (id INTEGER PRIMARY KEY, body TEXT); INSERT INTO notes VALUES (1, "newest");'
    )));

    $this->artisan('db:pull --force')->assertSuccessful();

    $restored = new PDO('sqlite:'.$this->target);

    expect($restored->query('SELECT body FROM notes')->fetchColumn())->toBe('newest');
});

it('leaves the existing database alone when the dump is broken', function () {
    file_put_contents($this->target, 'original');

    Storage::disk('r2')->put('database/2026-08-25-03-00-00.zip', file_get_contents(
        fakeBackup('THIS IS NOT SQL;')
    ));

    $this->artisan('db:pull --force')->assertFailed();

    expect(file_get_contents($this->target))->toBe('original');
});

it('stops when there is nothing to pull', function () {
    $this->artisan('db:pull --force')->assertFailed();
});

it('stops when no bucket is configured', function () {
    config()->set('filesystems.disks.r2.bucket', null);

    $this->artisan('db:pull --force')->assertFailed();
});
