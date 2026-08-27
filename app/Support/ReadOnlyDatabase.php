<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

/**
 * The database as the MCP server is allowed to see it: reads only, and only of
 * the tables named in `mcp.tables`.
 *
 * Three guards, each closing what the others cannot. The shape check refuses
 * anything that is not a single SELECT, so statements that are legal reads but
 * reach outside the query (ATTACH, PRAGMA) never run. The table check refuses
 * a SELECT that reads a table not on the allowlist. `PRAGMA query_only` then
 * refuses writes at the connection, so a statement the first check failed to
 * understand still cannot change anything.
 *
 * The connection is a separate one onto the same file, so `query_only` cannot
 * leak into the connection the application writes through.
 */
final class ReadOnlyDatabase
{
    public const DEFAULT_LIMIT = 100;

    public const MAX_LIMIT = 500;

    private const CONNECTION = 'sqlite_readonly';

    /** Characters of a stack trace worth returning before it stops helping. */
    private const EXCEPTION_CHARS = 1500;

    /**
     * Run one SELECT over the readable tables and return at most $limit rows.
     *
     * @return list<array<string, mixed>>
     *
     * @throws RuntimeException When the statement is not an allowed read.
     */
    public function select(string $sql, int $limit = self::DEFAULT_LIMIT): array
    {
        $this->guardShape($sql);
        $this->guardTables($sql);

        return $this->run($sql, $limit);
    }

    /**
     * The readable tables, with how many rows each holds. A table on the
     * allowlist that does not exist is left out rather than reported as empty.
     *
     * @return list<array<string, mixed>>
     */
    public function tables(): array
    {
        $existing = array_column($this->run(
            "select name from sqlite_master where type = 'table' order by name",
            self::MAX_LIMIT,
        ), 'name');

        $readable = array_values(array_intersect($existing, $this->readable()));

        return array_map(fn (string $name): array => [
            'table' => $name,
            'rows' => $this->run('select count(*) as total from "'.$name.'"', 1)[0]['total'] ?? 0,
        ], $readable);
    }

    /**
     * One readable table's columns, or an empty list when there is no such
     * readable table. A table that exists but is not on the allowlist is
     * indistinguishable from one that does not exist, which is the point.
     *
     * @return list<array<string, mixed>>
     */
    public function columns(string $table): array
    {
        if (! in_array($table, $this->readable(), true)) {
            return [];
        }

        return $this->run('select name, type, "notnull", dflt_value, pk from pragma_table_info('.$this->quote($table).')', self::MAX_LIMIT);
    }

    /**
     * Failed queue jobs, as what broke rather than what was being processed.
     *
     * `payload` is never returned: it carries whatever the job was handed, and
     * for the health export that is a person's raw readings. The job's class
     * name is lifted out of it, since that is the part worth seeing.
     *
     * @return list<array<string, mixed>>
     */
    public function failedJobs(int $limit = 10): array
    {
        $rows = $this->run('select id, uuid, connection, queue, payload, exception, failed_at from failed_jobs order by failed_at desc', $limit);

        return array_map(fn (array $row): array => [
            'id' => $row['id'],
            'uuid' => $row['uuid'],
            'queue' => $row['connection'].'/'.$row['queue'],
            'job' => json_decode((string) $row['payload'], true)['displayName'] ?? 'unknown',
            'failed_at' => $row['failed_at'],
            'exception' => mb_substr((string) $row['exception'], 0, self::EXCEPTION_CHARS),
        ], $rows);
    }

    /** Refuse anything that is not a single SELECT. */
    private function guardShape(string $sql): void
    {
        $statement = rtrim(trim($sql), "; \t\n\r");

        if ($statement === '') {
            throw new RuntimeException('No statement given.');
        }

        if (str_contains($statement, ';')) {
            throw new RuntimeException('One statement at a time.');
        }

        if (preg_match('/^(select|with)\b/i', $statement) !== 1) {
            throw new RuntimeException('Only SELECT is allowed here. Writes stay on the server.');
        }
    }

    /**
     * Refuse a statement that reads a table the server does not expose.
     *
     * Names are read from after FROM and JOIN, the only way a table is reached.
     * A name the same statement defines with `x as (...)` is a query rather
     * than a table, so it is permitted; a CTE sharing a table's name shadows
     * that table anyway, so it cannot be used to reach one. Unrecognised names
     * are refused, which is what keeps a table added later private by default.
     */
    private function guardTables(string $sql): void
    {
        preg_match_all('/\b(?:from|join)\s+["\'`\[]?([a-z_][a-z0-9_]*)/i', $sql, $referenced);
        preg_match_all('/([a-z_][a-z0-9_]*)\s+as\s*\(/i', $sql, $expressions);

        $permitted = array_merge($this->readable(), array_map('strtolower', $expressions[1]));

        foreach ($referenced[1] as $table) {
            if (! in_array(strtolower($table), $permitted, true)) {
                throw new RuntimeException("The table {$table} is not readable here. Use the schema tool to see which are.");
            }
        }
    }

    /**
     * @return list<string>
     */
    private function readable(): array
    {
        /** @var list<string> */
        return config('mcp.tables', []);
    }

    /**
     * Fetch rows without the allowlist check, for the statements this class
     * writes itself.
     *
     * @return list<array<string, mixed>>
     */
    private function run(string $sql, int $limit): array
    {
        $statement = $this->connection()->getPdo()->prepare($sql);
        $statement->execute();

        $capped = max(1, min($limit, self::MAX_LIMIT));
        $rows = [];

        // Fetched one at a time rather than through select(), so an unbounded
        // query cannot pull a whole table into memory before being trimmed.
        while (count($rows) < $capped && ($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $rows[] = $row;
        }

        $statement->closeCursor();

        return $rows;
    }

    private function quote(string $value): string
    {
        return $this->connection()->getPdo()->quote($value);
    }

    private function connection(): Connection
    {
        $connection = DB::connection(self::CONNECTION);

        // Idempotent, and cheap: the connection is resolved once per request.
        $connection->getPdo()->exec('PRAGMA query_only = 1');

        return $connection;
    }
}
