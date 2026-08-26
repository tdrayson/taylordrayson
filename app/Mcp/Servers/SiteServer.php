<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\DatabaseQuery;
use App\Mcp\Tools\DatabaseSchema;
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

Table names are singular and do not always match the model (sleep, not sleeps),
so call the schema tool before guessing at one.
TEXT)]
class SiteServer extends Server
{
    protected array $tools = [
        DatabaseQuery::class,
        DatabaseSchema::class,
        ReadLogs::class,
    ];
}
