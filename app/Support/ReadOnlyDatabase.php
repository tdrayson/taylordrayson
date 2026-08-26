<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

/**
 * The database as the MCP server is allowed to see it.
 *
 * Two independent guards, because either on its own is weak. `PRAGMA
 * query_only` refuses writes at the connection, so a statement this class
 * fails to understand still cannot change anything. The shape check refuses
 * anything that is not a single SELECT, so statements that are legal reads but
 * reach outside the database (ATTACH, PRAGMA) never run at all.
 *
 * The connection is a separate one onto the same file, so `query_only` cannot
 * leak into the connection the application writes through.
 */
final class ReadOnlyDatabase
{
    public const DEFAULT_LIMIT = 100;

    public const MAX_LIMIT = 500;

    private const CONNECTION = 'sqlite_readonly';

    /**
     * Run one SELECT and return at most $limit rows.
     *
     * @return list<array<string, mixed>>
     *
     * @throws RuntimeException When the statement is not a single read.
     */
    public function select(string $sql, int $limit = self::DEFAULT_LIMIT): array
    {
        $this->guard($sql);

        $statement = $this->connection()->getPdo()->prepare($sql);
        $statement->execute();

        $capped = max(1, min($limit, self::MAX_LIMIT));
        $rows = [];

        // Fetched one at a time rather than through select(), so an unbounded
        // query cannot pull the whole table into memory before being trimmed.
        while (count($rows) < $capped && ($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $rows[] = $row;
        }

        $statement->closeCursor();

        return $rows;
    }

    /**
     * Table names, with how many rows each holds.
     *
     * @return list<array<string, mixed>>
     */
    public function tables(): array
    {
        $names = array_column($this->select(
            "select name from sqlite_master where type = 'table' and name not like 'sqlite_%' order by name",
            self::MAX_LIMIT,
        ), 'name');

        return array_map(fn (string $name): array => [
            'table' => $name,
            'rows' => $this->select('select count(*) as total from "'.$name.'"')[0]['total'] ?? 0,
        ], $names);
    }

    /**
     * One table's columns, or an empty list when there is no such table.
     *
     * @return list<array<string, mixed>>
     */
    public function columns(string $table): array
    {
        // Quoted rather than interpolated raw: pragma_table_info is a table
        // function, so the name arrives as a bound value like any other.
        return $this->select('select name, type, "notnull", dflt_value, pk from pragma_table_info('.$this->quote($table).')', self::MAX_LIMIT);
    }

    /** Refuse anything that is not a single SELECT. */
    private function guard(string $sql): void
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
