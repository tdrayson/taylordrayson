<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Redirect Domains
    |--------------------------------------------------------------------------
    |
    | Where an OAuth client is allowed to have the authorization code sent.
    | The package ships "*", which would let anyone register a client that
    | redirects the code to a host they own; the login screen would look
    | entirely normal and the token would be theirs. Only Claude's own
    | callbacks belong here, plus localhost for Claude Code and the inspector.
    |
    */

    'redirect_domains' => [
        'https://claude.ai',
        'https://claude.com',
        'http://localhost',
    ],

    /*
    |--------------------------------------------------------------------------
    | Readable Tables
    |--------------------------------------------------------------------------
    |
    | The only tables the MCP server may read. An allowlist rather than a list
    | of exclusions, so a table added later is private until it is named here
    | rather than exposed until somebody notices.
    |
    | Deliberately absent: users, sessions and password_reset_tokens, which
    | hold credentials; the oauth_* tables; and the queue tables, whose
    | payloads carry whatever a job was given. Failed jobs are readable
    | through their own tool, which returns the error without the payload.
    |
    */

    'tables' => [
        'activities',
        'appearances',
        'articles',
        'attachments',
        'calories',
        'checkins',
        'events',
        'flights',
        'fuel',
        'leaderboard_entries',
        'media',
        'notes',
        'pages',
        'podcasts',
        'projects',
        'series',
        'sleep',
        'states',
        'taggables',
        'tags',
        'timeline_entries',
        'trips',
    ],

];
