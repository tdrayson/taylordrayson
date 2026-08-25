<?php

use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Config\Config;

/*
 * The database half of #44. Worth a test rather than trust: spatie dumps
 * SQLite through the `sqlite3` binary, so this fails on a machine without it,
 * and a backup that silently never runs is the failure mode being guarded
 * against in the first place.
 */

it('writes a database backup containing a dump', function () {
    Storage::fake('local');
    config(['backup.backup.destination.disks' => ['local']]);

    // Spatie resolves its config into a scoped object at boot, so overriding
    // the array alone would not reach it.
    app()->forgetInstance(Config::class);

    $this->artisan('backup:run --only-db')->assertSuccessful();

    $backups = collect(Storage::disk('local')->allFiles())
        ->filter(fn (string $path): bool => str_ends_with($path, '.zip'));

    expect($backups)->toHaveCount(1);

    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($backups->first()));

    $names = collect(range(0, $zip->numFiles - 1))->map(fn (int $i): string => $zip->getNameIndex($i));
    $zip->close();

    // The dump, and nothing else: `source.files.include` is empty on purpose,
    // because assets are mirrored rather than archived.
    expect($names)->toHaveCount(1)
        ->and($names->first())->toContain('.sql');
});
