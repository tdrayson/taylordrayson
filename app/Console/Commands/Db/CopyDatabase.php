<?php

namespace App\Console\Commands\Db;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Copies every row from a SQLite file into the app's database, primary keys and
 * all.
 *
 * Written for the move to MySQL. A SQL dump is the obvious route and the wrong
 * one: SQLite writes string literals verbatim, MySQL reads a backslash inside
 * one as an escape, and timeline_entries.timelineable_type holds a class name
 * with two of them in every row. A dump imports without a single error and
 * leaves the polymorphic relation broken on all of them. Going through PDO
 * means the driver does the escaping and that cannot happen.
 *
 * Primary keys are preserved deliberately: media files live in directories
 * named after their row id, so changing the ids would strand every attachment.
 */
#[Signature('db:copy
    {--from= : Path to the source SQLite file}
    {--to= : Target connection name (default: the app default)}
    {--chunk=250 : Rows per insert}
    {--pretend : Report what would be copied, and write nothing}')]
#[Description('Copy every row from a SQLite file into the target database, preserving ids')]
class CopyDatabase extends Command
{
    /**
     * Runtime state rather than content. The target rebuilds `migrations` by
     * running migrate, and the rest is cache, queue and session scratch that
     * would only carry stale entries onto a fresh install.
     *
     * @var list<string>
     */
    private const SKIP = [
        'migrations',
        'cache',
        'cache_locks',
        'sessions',
        'jobs',
        'job_batches',
        'failed_jobs',
        'password_reset_tokens',
    ];

    private const SOURCE_CONNECTION = 'db_copy_source';

    public function handle(): int
    {
        $path = (string) $this->option('from');

        if ($path === '' || ! is_file($path)) {
            $this->components->error("Source file not found: {$path}");

            return self::FAILURE;
        }

        $target = $this->option('to') ?: config('database.default');
        $this->registerSource($path);

        $source = DB::connection(self::SOURCE_CONNECTION);
        $to = DB::connection($target);

        $this->components->info(sprintf(
            'Copying %s into the %s connection (%s).',
            $path,
            $target,
            $to->getDatabaseName(),
        ));

        $tables = $this->tablesToCopy($source, $target);

        if ($tables === []) {
            $this->components->error('No tables in common. Has migrate been run on the target?');

            return self::FAILURE;
        }

        if (($mismatched = $this->columnMismatches($source, $target, $tables)) !== []) {
            $this->components->error('The two schemas disagree, so the copy would silently drop data:');

            foreach ($mismatched as $line) {
                $this->line("  {$line}");
            }

            return self::FAILURE;
        }

        return $this->option('pretend')
            ? $this->reportOnly($source, $tables)
            : $this->copy($source, $to, $tables);
    }

    /**
     * A connection pointed at the source file, defined at runtime so no config
     * entry is needed and the file can live anywhere.
     */
    private function registerSource(string $path): void
    {
        config([
            'database.connections.'.self::SOURCE_CONNECTION => [
                'driver' => 'sqlite',
                'database' => $path,
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);
    }

    /**
     * Tables present on both sides, minus the runtime ones.
     *
     * @return list<string>
     */
    private function tablesToCopy(Connection $source, string $target): array
    {
        $names = array_column($source->getSchemaBuilder()->getTables(), 'name');

        return array_values(array_filter(
            $names,
            fn (string $table): bool => ! in_array($table, self::SKIP, true)
                && Schema::connection($target)->hasTable($table),
        ));
    }

    /**
     * Columns on one side and not the other.
     *
     * Checked up front rather than discovered mid-copy: a source column the
     * target lacks would be dropped by the insert without complaint, which is
     * the one failure here that leaves no trace.
     *
     * @param  list<string>  $tables
     * @return list<string>
     */
    private function columnMismatches(Connection $source, string $target, array $tables): array
    {
        $problems = [];

        foreach ($tables as $table) {
            $from = $source->getSchemaBuilder()->getColumnListing($table);
            $to = Schema::connection($target)->getColumnListing($table);

            if ($missing = array_diff($from, $to)) {
                $problems[] = "{$table}: missing on the target: ".implode(', ', $missing);
            }

            if ($extra = array_diff($to, $from)) {
                $problems[] = "{$table}: only on the target: ".implode(', ', $extra);
            }
        }

        return $problems;
    }

    /**
     * @param  list<string>  $tables
     */
    private function reportOnly(Connection $source, array $tables): int
    {
        foreach ($tables as $table) {
            $this->components->twoColumnDetail($table, number_format($source->table($table)->count()).' rows');
        }

        $this->components->info('Nothing written (--pretend).');

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $tables
     */
    private function copy(Connection $source, Connection $to, array $tables): int
    {
        // Rows are copied in whatever order the tables come back in, so the
        // constraints are stood down for the duration rather than the tables
        // being topologically sorted. Restored when the closure returns.
        $to->getSchemaBuilder()->withoutForeignKeyConstraints(function () use ($source, $to, $tables): void {
            foreach ($tables as $table) {
                $this->copyTable($source, $to, $table);
            }
        });

        return $this->verify($source, $to, $tables);
    }

    private function copyTable(Connection $source, Connection $to, string $table): void
    {
        $total = $source->table($table)->count();

        if ($total === 0) {
            $this->components->twoColumnDetail($table, '<fg=gray>empty</>');

            return;
        }

        $to->table($table)->delete();

        $chunk = max(1, (int) $this->option('chunk'));
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        // Keyset paginated on rowid, SQLite's own row identifier, so this works
        // on pivot tables with no id column of their own.
        $after = 0;

        while (true) {
            $rows = $source->select(
                "SELECT rowid AS db_copy_rowid, * FROM \"{$table}\" WHERE rowid > ? ORDER BY rowid LIMIT ?",
                [$after, $chunk],
            );

            if ($rows === []) {
                break;
            }

            $after = end($rows)->db_copy_rowid;

            $records = array_map(function (object $row): array {
                $values = (array) $row;
                unset($values['db_copy_rowid']);

                return $values;
            }, $rows);

            $this->insert($to, $table, $records);
            $bar->advance(count($records));
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Insert a chunk, falling back to one row at a time if it is rejected.
     *
     * The fallback is for the report, not the recovery: a bad chunk says only
     * that something in those 250 rows was refused, which is no use against
     * 25,000 of them. Retrying individually names the row.
     *
     * @param  list<array<string, mixed>>  $records
     */
    private function insert(Connection $to, string $table, array $records): void
    {
        try {
            $to->table($table)->insert($records);

            return;
        } catch (Throwable) {
            // Fall through and find the row responsible.
        }

        foreach ($records as $record) {
            try {
                $to->table($table)->insert($record);
            } catch (Throwable $exception) {
                $key = $record['id'] ?? '(no id)';
                $this->newLine();
                $this->components->warn("{$table} #{$key} refused: {$exception->getMessage()}");
            }
        }
    }

    /**
     * @param  list<string>  $tables
     */
    private function verify(Connection $source, Connection $to, array $tables): int
    {
        $this->newLine();
        $this->components->info('Verifying row counts.');

        $wrong = 0;

        foreach ($tables as $table) {
            $expected = $source->table($table)->count();
            $actual = $to->table($table)->count();
            $ok = $expected === $actual;
            $wrong += $ok ? 0 : 1;

            $this->components->twoColumnDetail(
                $table,
                $ok
                    ? number_format($actual).' <fg=green>✓</>'
                    : "<fg=red>{$actual} of {$expected}</>",
            );
        }

        if ($wrong > 0) {
            $this->components->error("{$wrong} table(s) did not copy completely.");

            return self::FAILURE;
        }

        $this->components->info('Every table matches.');

        return self::SUCCESS;
    }
}
