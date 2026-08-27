<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\DatabaseQuery;
use App\Mcp\Tools\DatabaseSchema;
use App\Mcp\Tools\FailedJobs;
use App\Mcp\Tools\ReadLogs;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('taylordrayson.com')]
#[Version('1.0.0')]
#[Instructions(<<<'TEXT'
Read-only access to the live site: query the database, inspect its schema, and
read the application log. Nothing here can write, and writes are not available
by any other tool either.

Only the site's own data tables are readable. Users, sessions, credentials and
queue payloads are not exposed, and asking for one reads as though it does not
exist. Failed jobs have their own tool, which returns the error without the
payload.

Table names are singular and do not always match the model (sleep, not sleeps),
so call the schema tool before guessing at one.
TEXT)]
class SiteServer extends Server
{
    protected array $tools = [
        DatabaseQuery::class,
        DatabaseSchema::class,
        ReadLogs::class,
        FailedJobs::class,
    ];
}
