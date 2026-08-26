<?php

use App\Support\ReadOnlyDatabase;
use Illuminate\Support\Facades\DB;

/*
 * The MCP server exposes the live database to a remote client, so the only
 * thing worth testing hard is that it cannot write. Two independent guards:
 * the statement shape, and `PRAGMA query_only` on the connection underneath.
 */

function readOnly(): ReadOnlyDatabase
{
    return app(ReadOnlyDatabase::class);
}

/** A real file, because two `:memory:` connections are two separate databases. */
function pointReadOnlyAtTempFile(): string
{
    $path = tempnam(sys_get_temp_dir(), 'mcp').'.sqlite';
    touch($path);

    config(['database.connections.sqlite_readonly.database' => $path]);
    DB::purge('sqlite_readonly');

    DB::connection('sqlite_readonly')->getPdo()->exec('create table notes (id integer primary key, body text)');
    DB::connection('sqlite_readonly')->getPdo()->exec("insert into notes (body) values ('one'), ('two'), ('three')");

    return $path;
}

it('refuses anything that is not a select', function (string $sql) {
    expect(fn () => readOnly()->select($sql))->toThrow(RuntimeException::class);
})->with([
    'a write' => 'update sleep set score = 100',
    'a delete' => 'delete from sleep',
    'a drop' => 'drop table sleep',
    'an attach' => "attach database '/etc/passwd' as leak",
    'a pragma' => 'pragma table_info(sleep)',
    'two statements' => 'select 1; drop table sleep',
    'a select with a write behind it' => 'select 1; update sleep set score = 0',
    'nothing at all' => '   ',
]);

it('allows a select and a common table expression', function () {
    pointReadOnlyAtTempFile();

    expect(readOnly()->select('select body from notes order by id'))->toHaveCount(3)
        ->and(readOnly()->select('with recent as (select * from notes) select count(*) as n from recent')[0]['n'])->toBe(3);
});

it('caps the rows it returns', function () {
    pointReadOnlyAtTempFile();

    expect(readOnly()->select('select * from notes', 2))->toHaveCount(2);
});

// The guard that matters: even a write the shape check failed to recognise is
// refused by the connection itself.
it('blocks a write at the connection, not only by inspecting the sql', function () {
    pointReadOnlyAtTempFile();

    // Warm the connection so query_only is set, then go around the shape check.
    readOnly()->select('select 1 as ok');

    expect(fn () => DB::connection('sqlite_readonly')->getPdo()->exec("insert into notes (body) values ('four')"))
        ->toThrow(PDOException::class);
});

it('lists tables and columns', function () {
    pointReadOnlyAtTempFile();

    expect(collect(readOnly()->tables())->pluck('table'))->toContain('notes')
        ->and(collect(readOnly()->columns('notes'))->pluck('name'))->toContain('body')
        ->and(readOnly()->columns('no_such_table'))->toBe([]);
});

it('refuses an unauthenticated caller', function () {
    $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertUnauthorized();
});
