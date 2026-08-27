<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\DatabaseQuery;
use App\Mcp\Tools\DatabaseSchema;
use App\Mcp\Tools\DataFreshness;
use App\Mcp\Tools\Entry;
use App\Mcp\Tools\EntrySeries;
use App\Mcp\Tools\FailedJobs;
use App\Mcp\Tools\ReadLogs;
use App\Mcp\Tools\SearchEntries;
use App\Mcp\Tools\SearchFields;
use App\Mcp\Tools\Stats;
use App\Mcp\Tools\Timeline;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('taylordrayson.com')]
#[Version('2.0.0')]
#[Instructions(<<<'TEXT'
Read-only access to a personal site: a timeline of sleep, activities, food,
flights, places, media, notes and more. Nothing here can write.

Reach for these first, in roughly this order:

- search_fields, then search_entries, for anything that is a question about
  matching a condition. This is the general way in: every type, with typed
  fields and operators.
- timeline, for what happened on a day or across a range.
- entry, for one thing in full, then entry_series when a GPS track or a night's
  sleep stages is actually needed. Those are large and are never returned
  unasked.
- stats, for totals and averages over a period.
- data_freshness, to see whether a kind of data is still arriving and how far
  behind it is running.

database_query and database_schema are the fallback, for derived columns
nothing presents and cross-type aggregates the search compiler cannot express.
Prefer the tools above: they return the same shapes the site itself renders,
and they do not require knowing the schema. Table names are singular and do not
always match the model (sleep, not sleeps).

Only the site's own data tables are readable. Users, sessions, credentials and
queue payloads are not exposed, and asking for one reads as though it does not
exist.
TEXT)]
class SiteServer extends Server
{
    protected array $tools = [
        SearchFields::class,
        SearchEntries::class,
        Timeline::class,
        Entry::class,
        EntrySeries::class,
        Stats::class,
        DataFreshness::class,
        FailedJobs::class,
        ReadLogs::class,
        DatabaseSchema::class,
        DatabaseQuery::class,
    ];
}
