<?php

namespace App\Console\Commands\Db;

use FilesystemIterator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

/**
 * Restore the local database from the newest backup in R2.
 *
 * Reads what production already uploads rather than touching the server, so no
 * SSH is involved and R2 charges nothing for egress. Copying the live SQLite
 * file instead would risk a torn snapshot, since the queue worker writes
 * constantly; the backup is a dump taken under `BEGIN IMMEDIATE`.
 */
#[Signature('db:pull {--force : Overwrite without confirming}')]
#[Description('Replace the local database with the newest backup from R2')]
class PullDatabase extends Command
{
    public function handle(): int
    {
        if (blank(config('filesystems.disks.r2.bucket'))) {
            $this->components->error('No R2 bucket configured; nothing to pull from.');

            return self::FAILURE;
        }

        $target = (string) config('database.connections.sqlite.database');

        if ($target === '' || $target === ':memory:') {
            $this->components->error('Only a file-backed SQLite database can be replaced.');

            return self::FAILURE;
        }

        $backup = $this->newestBackup();

        if ($backup === null) {
            $this->components->error('No backups found in R2.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Replace {$target} with {$backup}?")) {
            return self::SUCCESS;
        }

        $workspace = $this->workspace();

        try {
            return $this->restore($backup, $target, $workspace);
        } finally {
            $this->discard($workspace);
        }
    }

    /**
     * The newest archive, by the timestamp spatie names it with. Sorting on the
     * name rather than asking the disk for modified times keeps this to one
     * listing call instead of one per file.
     */
    private function newestBackup(): ?string
    {
        $files = collect(Storage::disk('r2')->files((string) config('backup.backup.name')))
            ->filter(fn (string $path): bool => str_ends_with($path, '.zip'))
            ->sortDesc();

        return $files->first();
    }

    /** Download, unpack and restore, leaving the live file untouched until the end. */
    private function restore(string $backup, string $target, string $workspace): int
    {
        $archive = $workspace.'/backup.zip';
        $stream = Storage::disk('r2')->readStream($backup);

        if ($stream === null) {
            $this->components->error("Could not read {$backup}.");

            return self::FAILURE;
        }

        file_put_contents($archive, $stream);
        $this->components->info('Downloaded '.$this->size($archive).'.');

        $dump = $this->extractDump($archive, $workspace);

        if ($dump === null) {
            return self::FAILURE;
        }

        $rebuilt = $workspace.'/restored.sqlite';

        if (! $this->rebuild($dump, $rebuilt)) {
            return self::FAILURE;
        }

        // Swapped in only once it is known to be a readable database, so a
        // failed restore leaves what was already there.
        rename($rebuilt, $target);

        $this->components->info('Local database replaced from '.$backup.'.');

        return self::SUCCESS;
    }

    /** Pull the SQL dump out of the archive, decrypting when one is configured. */
    private function extractDump(string $archive, string $workspace): ?string
    {
        $zip = new ZipArchive;

        if ($zip->open($archive) !== true) {
            $this->components->error('Could not open the downloaded archive.');

            return null;
        }

        $password = config('backup.backup.password');

        if (filled($password)) {
            $zip->setPassword((string) $password);
        }

        $entry = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (str_ends_with($name, '.sql')) {
                $entry = $name;

                break;
            }
        }

        if ($entry === null) {
            $zip->close();
            $this->components->error('The archive holds no SQL dump.');

            return null;
        }

        $extracted = $zip->extractTo($workspace, $entry);
        $zip->close();

        if (! $extracted) {
            $this->components->error('Could not extract the dump; check BACKUP_ARCHIVE_PASSWORD.');

            return null;
        }

        return $workspace.'/'.$entry;
    }

    /**
     * Build a database from the dump and prove it opens before it is trusted.
     * Uses the sqlite3 binary, the same one the dump was written by.
     */
    private function rebuild(string $dump, string $destination): bool
    {
        $restore = Process::fromShellCommandline(
            sprintf('sqlite3 --bail %s < %s', escapeshellarg($destination), escapeshellarg($dump)),
        );
        $restore->setTimeout(null);
        $restore->run();

        if (! $restore->isSuccessful()) {
            $this->components->error('Restore failed: '.trim($restore->getErrorOutput()));

            return false;
        }

        $check = Process::fromShellCommandline(
            sprintf('sqlite3 %s "PRAGMA integrity_check;"', escapeshellarg($destination)),
        );
        $check->run();

        if (trim($check->getOutput()) !== 'ok') {
            $this->components->error('Restored file failed its integrity check.');

            return false;
        }

        return true;
    }

    private function workspace(): string
    {
        $path = storage_path('app/db-pull-'.bin2hex(random_bytes(4)));
        mkdir($path, 0755, true);

        return $path;
    }

    /** The dump sits in a `db-dumps/` subdirectory, so this has to recurse. */
    private function discard(string $workspace): void
    {
        try {
            $entries = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($workspace, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($entries as $entry) {
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }

            rmdir($workspace);
        } catch (Throwable) {
            // A leftover temp directory is not worth failing a restore over.
        }
    }

    private function size(string $path): string
    {
        return round(filesize($path) / 1024 / 1024, 1).'MB';
    }
}
