<?php

use App\Models\User;
use App\Support\ReadOnlyDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

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

    $pdo = DB::connection('sqlite_readonly')->getPdo();
    $pdo->exec('create table notes (id integer primary key, body text)');
    $pdo->exec("insert into notes (body) values ('one'), ('two'), ('three')");

    // Present in the file but absent from the allowlist, which is what the
    // scoping tests below turn on.
    $pdo->exec('create table users (id integer primary key, email text, password text)');
    $pdo->exec("insert into users (email, password) values ('taylor@example.com', 'hash')");
    $pdo->exec('create table failed_jobs (id integer primary key, uuid text, connection text, queue text, payload text, exception text, failed_at text)');
    $pdo->exec("insert into failed_jobs (uuid, connection, queue, payload, exception, failed_at) values ('abc', 'redis', 'default', '{\"displayName\":\"App\\\\Jobs\\\\ProcessHealthExport\",\"secret\":\"heart rate readings\"}', 'RuntimeException: it broke', '2026-08-26 10:00:00')");

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

describe('scoping', function () {
    it('refuses a table that is not on the allowlist', function (string $sql) {
        expect(fn () => readOnly()->select($sql))->toThrow(RuntimeException::class);
    })->with([
        'users' => 'select * from users',
        'sessions' => 'select ip_address from sessions',
        'queue payloads' => 'select payload from failed_jobs',
        'a join onto users' => 'select * from notes join users on users.id = notes.id',
        'a subquery' => 'select (select email from users) as leak',
    ]);

    it('still allows the tables it does expose', function () {
        pointReadOnlyAtTempFile();

        expect(readOnly()->select('select body from notes'))->toHaveCount(3)
            ->and(readOnly()->select('with mine as (select * from notes) select count(*) as n from mine')[0]['n'])->toBe(3);
    });

    it('hides an unreadable table from the schema entirely', function () {
        pointReadOnlyAtTempFile();

        expect(collect(readOnly()->tables())->pluck('table'))->toContain('notes')->not->toContain('users')
            ->and(readOnly()->columns('users'))->toBe([]);
    });

    it('returns a failed job without its payload', function () {
        pointReadOnlyAtTempFile();

        $job = readOnly()->failedJobs()[0];

        expect($job['job'])->toBe('App\\Jobs\\ProcessHealthExport')
            ->and($job['exception'])->toContain('it broke')
            ->and(json_encode($job))->not->toContain('heart rate readings');
    });
});

describe('the oauth flow', function () {
    function claudeClient(): object
    {
        return app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            name: 'Claude',
            redirectUris: ['https://claude.ai/api/mcp/auth_callback'],
            confidential: false,
        );
    }

    // Passport ships no consent screen, so without one registered this step of
    // the flow 500s and the connector never completes.
    it('renders a consent screen', function () {
        $client = claudeClient();

        $this->actingAs(User::factory()->create())
            ->get('/oauth/authorize?'.http_build_query([
                'client_id' => $client->id,
                'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
                'response_type' => 'code',
                'scope' => 'mcp:use',
                'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', 'verifier', true)), '+/', '-_'), '='),
                'code_challenge_method' => 'S256',
            ]))
            ->assertOk();
    });

    it('publishes the discovery documents a connector looks for', function () {
        $this->getJson('/.well-known/oauth-protected-resource')->assertOk()
            ->assertJsonPath('scopes_supported.0', 'mcp:use');

        $this->getJson('/.well-known/oauth-authorization-server')->assertOk()
            ->assertJsonPath('code_challenge_methods_supported.0', 'S256');
    });

    // The control that stops a stranger registering a client which sends the
    // authorization code to a host they own.
    it('refuses to register a client redirecting somewhere unexpected', function () {
        $this->postJson('/oauth/register', [
            'client_name' => 'Not Claude',
            'redirect_uris' => ['https://evil.example.com/callback'],
        ])->assertStatus(400);

        // Asserted on the outcome as well as the status, which the package has
        // already changed once: nothing may be left behind to authorise later.
        expect(Client::query()->count())->toBe(0);
    });

    it('registers a client redirecting to claude', function () {
        $this->postJson('/oauth/register', [
            'client_name' => 'Claude',
            'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
        ])->assertOk()->assertJsonPath('scope', 'mcp:use');
    });
});

// laravel/mcp first arrived as a transitive dependency of laravel/boost, which
// is require-dev. The deploy installs --no-dev, so the package was absent in
// production, the service provider never ran, routes/ai.php was never loaded,
// and every MCP route 404'd while the code sat there looking correct.
it('depends on laravel/mcp in production, not only in development', function () {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

    expect($composer['require'])->toHaveKey('laravel/mcp')
        ->and($composer['require'])->toHaveKey('laravel/passport');
});
